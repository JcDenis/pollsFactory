<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Crypt;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * @brief       pollsFactory polls tools.
 * @ingroup     pollsFactory
 *
 * Polls are entries of type "pollsfactory",
 * queries, selections, responses and links to entries
 * are stored in table "post_option".
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Polls
{
    /**
     * Get options table name (with prefix).
     */
    public static function table(): string
    {
        return App::db()->con()->prefix() . My::TABLE_NAME;
    }

    /**
     * Open options table cursor.
     */
    public static function openCursor(): Cursor
    {
        return App::db()->con()->openCursor(self::table());
    }

    /// @name Polls methods
    //@{
    /**
     * Get polls.
     *
     * @param   array<string, mixed>    $params         The query parameters (see Blog::getPosts())
     * @param   bool                    $count_only     Count only
     */
    public static function getPolls(array $params = [], bool $count_only = false): MetaRecord
    {
        $params['post_type'] = My::POST_TYPE;

        return App::blog()->getPosts($params, $count_only);
    }

    /**
     * Get a poll.
     *
     * @param   int     $poll_id        The poll ID
     * @param   bool    $no_content     Do not get content
     */
    public static function getPoll(int $poll_id, bool $no_content = false): MetaRecord
    {
        if ($poll_id < 1) {
            return MetaRecord::newFromArray([]);
        }

        return self::getPolls([
            'post_id'    => $poll_id,
            'no_content' => $no_content,
            'limit'      => 1,
        ]);
    }

    /**
     * Open or close votes of a poll.
     *
     * @param   int     $poll_id    The poll ID
     * @param   bool    $opened     Votes are opened
     */
    public static function setOpened(int $poll_id, bool $opened): void
    {
        $sql = new UpdateStatement();
        $sql->ref(App::db()->con()->prefix() . App::blog()::POST_TABLE_NAME)
            ->column('post_open_tb')
            ->value((int) $opened)
            ->where('post_id = ' . $poll_id)
            ->and('post_type = ' . $sql->quote(My::POST_TYPE))
            ->and('blog_id = ' . $sql->quote(App::blog()->id()))
            ->update();

        App::blog()->triggerBlog();
    }

    /**
     * Delete all options of a poll (queries, selections, responses, links).
     *
     * @param   int     $poll_id    The poll ID
     */
    public static function delPollOptions(int $poll_id): void
    {
        $sql = new DeleteStatement();
        $sql->from(self::table())
            ->where('post_id = ' . $poll_id)
            ->and('option_type' . $sql->in([My::TYPE_QUERY, My::TYPE_SELECTION, My::TYPE_RESPONSE]))
            ->delete();

        $sql = new DeleteStatement();
        $sql->from(self::table())
            ->where('option_type = ' . $sql->quote(My::TYPE_POST))
            ->and('option_meta = ' . $sql->quote((string) $poll_id))
            ->delete();
    }
    //@}

    /// @name Options methods
    //@{
    /**
     * Get options.
     *
     * Parameters:
     * - option_type (string|array) : The option type (required)
     * - option_id (int|array)
     * - post_id (int|array)
     * - option_meta (string|array)
     * - option_title (string)
     * - option_content (string)
     * - option_selected (int)
     * - order (string)
     * - limit (int|array)
     *
     * @param   array<string, mixed>    $params         The query parameters
     * @param   bool                    $count_only     Count only
     */
    public static function getOptions(array $params, bool $count_only = false): MetaRecord
    {
        $sql = new SelectStatement();

        if ($count_only) {
            $sql->column($sql->count('O.option_id'));
        } else {
            $sql->columns([
                'O.option_id',
                'O.post_id',
                'O.option_meta',
                'O.option_creadt',
                'O.option_upddt',
                'O.option_type',
                'O.option_format',
                'O.option_lang',
                'O.option_title',
                'O.option_content',
                'O.option_content_xhtml',
                'O.option_selected',
                'O.option_position',
                'P.post_type',
                'P.post_title',
            ]);
        }

        $sql->from($sql->as(self::table(), 'O'))
            ->join(
                (new JoinStatement())
                    ->inner()
                    ->from($sql->as(App::db()->con()->prefix() . App::blog()::POST_TABLE_NAME, 'P'))
                    ->on('P.post_id = O.post_id')
                    ->statement()
            )
            ->where('P.blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('O.option_type' . $sql->in(self::strings($params['option_type'] ?? '')));

        if (isset($params['option_id'])) {
            $sql->and('O.option_id' . $sql->in(self::ints($params['option_id'])));
        }
        if (isset($params['post_id'])) {
            $sql->and('O.post_id' . $sql->in(self::ints($params['post_id'])));
        }
        if (isset($params['option_meta'])) {
            $sql->and('O.option_meta' . $sql->in(self::strings($params['option_meta'])));
        }
        if (isset($params['option_title']) && is_string($params['option_title'])) {
            $sql->and('O.option_title = ' . $sql->quote($params['option_title']));
        }
        if (isset($params['option_content']) && is_string($params['option_content'])) {
            $sql->and('O.option_content = ' . $sql->quote($params['option_content']));
        }
        if (isset($params['option_selected']) && is_numeric($params['option_selected'])) {
            $sql->and('O.option_selected = ' . (int) $params['option_selected']);
        }

        if (!$count_only) {
            $sql->order(isset($params['order']) && is_string($params['order']) && $params['order'] !== '' ? $sql->escape($params['order']) : 'O.option_position ASC, O.option_id ASC');

            if (isset($params['limit'])) {
                if (is_numeric($params['limit'])) {
                    $sql->limit((int) $params['limit']);
                } elseif (is_array($params['limit']) && is_numeric($params['limit'][0] ?? null) && is_numeric($params['limit'][1] ?? null)) {
                    $sql->limit([(int) $params['limit'][0], (int) $params['limit'][1]]);
                }
            }
        }

        return $sql->select() ?? MetaRecord::newFromArray([]);
    }

    /**
     * Add an option.
     *
     * @param   Cursor  $cur    The option cursor
     *
     * @return  int     The new option ID
     */
    public static function addOption(Cursor $cur): int
    {
        App::db()->con()->writeLock(self::table());

        try {
            $sql = new SelectStatement();
            $rs  = $sql->from(self::table())
                ->column($sql->max('option_id'))
                ->select();

            $id = ($rs instanceof MetaRecord ? $rs->intField(0) : 0) + 1;

            $cur->setField('option_id', $id);
            $cur->setField('option_creadt', date('Y-m-d H:i:s'));
            $cur->setField('option_upddt', date('Y-m-d H:i:s'));
            if (!is_string($cur->getField('option_content_xhtml'))) {
                $cur->setField('option_content_xhtml', '');
            }
            if (!is_string($cur->getField('option_format'))) {
                $cur->setField('option_format', 'xhtml');
            }
            $cur->insert();

            App::db()->con()->unlock();
        } catch (Exception $e) {
            App::db()->con()->unlock();

            throw $e;
        }

        App::blog()->triggerBlog();

        return $id;
    }

    /**
     * Update an option.
     *
     * @param   int     $option_id  The option ID
     * @param   Cursor  $cur        The option cursor
     */
    public static function updOption(int $option_id, Cursor $cur): void
    {
        $cur->setField('option_upddt', date('Y-m-d H:i:s'));
        $cur->update('WHERE option_id = ' . $option_id . ' ');

        App::blog()->triggerBlog();
    }

    /**
     * Get next position of an option.
     *
     * @param   int     $poll_id        The poll ID
     * @param   string  $option_type    The option type
     * @param   string  $option_meta    The option meta (or empty string)
     */
    public static function nextPosition(int $poll_id, string $option_type, string $option_meta = ''): int
    {
        $sql = new SelectStatement();
        $sql->from(self::table())
            ->column($sql->max('option_position'))
            ->where('post_id = ' . $poll_id)
            ->and('option_type = ' . $sql->quote($option_type));

        if ($option_meta !== '') {
            $sql->and('option_meta = ' . $sql->quote($option_meta));
        }

        $rs = $sql->select();

        return ($rs instanceof MetaRecord ? $rs->intField(0) : 0) + 1;
    }

    /**
     * Move an option up or down.
     *
     * @param   MetaRecord  $rs         The options (in current order)
     * @param   int         $option_id  The option to move
     * @param   bool        $up         Move up (or down)
     */
    public static function move(MetaRecord $rs, int $option_id, bool $up): void
    {
        $ids = [];
        while ($rs->fetch()) {
            $ids[] = $rs->intField('option_id');
        }

        $pos = array_search($option_id, $ids, true);
        if ($pos === false) {
            return;
        }
        $to = $up ? $pos - 1 : $pos + 1;
        if (!isset($ids[$to])) {
            return;
        }

        [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];

        foreach ($ids as $k => $id) {
            $sql = new UpdateStatement();
            $sql->ref(self::table())
                ->column('option_position')
                ->value($k + 1)
                ->where('option_id = ' . $id)
                ->update();
        }

        App::blog()->triggerBlog();
    }
    //@}

    /// @name Queries methods
    //@{
    /**
     * Get queries of a poll.
     *
     * @param   int     $poll_id    The poll ID
     */
    public static function getQueries(int $poll_id): MetaRecord
    {
        return self::getOptions([
            'option_type' => My::TYPE_QUERY,
            'post_id'     => $poll_id,
        ]);
    }

    /**
     * Get selections of a poll.
     *
     * @param   int     $poll_id    The poll ID
     * @param   int     $query_id   The query ID (or 0 for all queries)
     */
    public static function getSelections(int $poll_id, int $query_id = 0): MetaRecord
    {
        $params = [
            'option_type' => My::TYPE_SELECTION,
            'post_id'     => $poll_id,
        ];
        if ($query_id !== 0) {
            $params['option_meta'] = (string) $query_id;
        }

        return self::getOptions($params);
    }

    /**
     * Delete a query, its selections and its responses.
     *
     * @param   int     $poll_id    The poll ID
     * @param   int     $query_id   The query ID
     */
    public static function delQuery(int $poll_id, int $query_id): void
    {
        $sql = new DeleteStatement();
        $sql->from(self::table())
            ->where('post_id = ' . $poll_id)
            ->and('option_id = ' . $query_id)
            ->and('option_type = ' . $sql->quote(My::TYPE_QUERY))
            ->delete();

        $sql = new DeleteStatement();
        $sql->from(self::table())
            ->where('post_id = ' . $poll_id)
            ->and('option_meta = ' . $sql->quote((string) $query_id))
            ->and('option_type' . $sql->in([My::TYPE_SELECTION, My::TYPE_RESPONSE]))
            ->delete();

        App::blog()->triggerBlog();
    }

    /**
     * Delete a selection and its responses.
     *
     * @param   int     $poll_id        The poll ID
     * @param   int     $selection_id   The selection ID
     */
    public static function delSelection(int $poll_id, int $selection_id): void
    {
        $sql = new DeleteStatement();
        $sql->from(self::table())
            ->where('post_id = ' . $poll_id)
            ->and('option_id = ' . $selection_id)
            ->and('option_type = ' . $sql->quote(My::TYPE_SELECTION))
            ->delete();

        $sql = new DeleteStatement();
        $sql->from(self::table())
            ->where('post_id = ' . $poll_id)
            ->and('option_content = ' . $sql->quote((string) $selection_id))
            ->and('option_type = ' . $sql->quote(My::TYPE_RESPONSE))
            ->delete();

        App::blog()->triggerBlog();
    }
    //@}

    /// @name Votes methods
    //@{
    /**
     * Get responses of a poll.
     *
     * @param   int     $poll_id    The poll ID
     * @param   int     $query_id   The query ID (or 0 for all queries)
     */
    public static function getResponses(int $poll_id, int $query_id = 0): MetaRecord
    {
        $params = [
            'option_type' => My::TYPE_RESPONSE,
            'post_id'     => $poll_id,
            'order'       => 'O.option_creadt DESC, O.option_id ASC',
        ];
        if ($query_id !== 0) {
            $params['option_meta'] = (string) $query_id;
        }

        return self::getOptions($params);
    }

    /**
     * Count voters of a poll.
     *
     * @param   int     $poll_id    The poll ID
     */
    public static function countVoters(int $poll_id): int
    {
        $sql = new SelectStatement();
        $rs  = $sql->from(self::table())
            ->column($sql->count($sql->unique('option_title')))
            ->where('post_id = ' . $poll_id)
            ->and('option_type = ' . $sql->quote(My::TYPE_RESPONSE))
            ->select();

        return $rs instanceof MetaRecord ? $rs->intField(0) : 0;
    }

    /**
     * Get voters of a poll.
     *
     * @param   int     $poll_id    The poll ID
     *
     * @return  array<string, string>   The voters IDs and votes dates
     */
    public static function getVoters(int $poll_id): array
    {
        $sql = new SelectStatement();
        $rs  = $sql->from(self::table())
            ->columns([
                'option_title',
                $sql->min('option_creadt', 'vote_dt'),
            ])
            ->where('post_id = ' . $poll_id)
            ->and('option_type = ' . $sql->quote(My::TYPE_RESPONSE))
            ->group('option_title')
            ->order('vote_dt DESC')
            ->select();

        $res = [];
        if ($rs instanceof MetaRecord) {
            while ($rs->fetch()) {
                $res[$rs->strField('option_title')] = $rs->strField('vote_dt');
            }
        }

        return $res;
    }

    /**
     * Delete responses of a voter.
     *
     * @param   int     $poll_id    The poll ID
     * @param   string  $voter      The voter ID
     */
    public static function delVoter(int $poll_id, string $voter): void
    {
        $sql = new DeleteStatement();
        $sql->from(self::table())
            ->where('post_id = ' . $poll_id)
            ->and('option_title = ' . $sql->quote($voter))
            ->and('option_type = ' . $sql->quote(My::TYPE_RESPONSE))
            ->delete();

        App::blog()->triggerBlog();
    }

    /**
     * Mark a text response as (un)selected for public display.
     *
     * @param   int     $poll_id        The poll ID
     * @param   int     $response_id    The response ID
     * @param   bool    $selected       Selected
     */
    public static function setResponseSelected(int $poll_id, int $response_id, bool $selected): void
    {
        $sql = new UpdateStatement();
        $sql->ref(self::table())
            ->column('option_selected')
            ->value((int) $selected)
            ->where('post_id = ' . $poll_id)
            ->and('option_id = ' . $response_id)
            ->and('option_type = ' . $sql->quote(My::TYPE_RESPONSE))
            ->update();

        App::blog()->triggerBlog();
    }

    /**
     * Get polls IDs voted by current visitor from cookie.
     *
     * @return  array<int, int>
     */
    public static function getCookieVotes(): array
    {
        $cookie = $_COOKIE[My::COOKIE_NAME] ?? '';

        return is_string($cookie) ? array_values(array_filter(array_map(intval(...), explode(',', $cookie)))) : [];
    }

    /**
     * Get hashed IP of current visitor.
     */
    public static function getIpHash(): string
    {
        return 'ip:' . substr(Crypt::hmac(App::config()->masterKey(), Http::realIP() ?? ''), 0, 40);
    }

    /**
     * Check if current visitor has voted on a poll.
     *
     * @param   int     $poll_id    The poll ID
     */
    public static function hasVoted(int $poll_id): bool
    {
        $ident = My::userIdent();

        if ($ident !== My::IDENT_IP && in_array($poll_id, self::getCookieVotes(), true)) {
            return true;
        }

        if ($ident !== My::IDENT_COOKIE) {
            return !self::getOptions([
                'option_type'  => My::TYPE_RESPONSE,
                'post_id'      => $poll_id,
                'option_title' => self::getIpHash(),
                'limit'        => 1,
            ])->isEmpty();
        }

        return false;
    }

    /**
     * Add votes of current visitor.
     *
     * @param   MetaRecord                                  $poll       The poll
     * @param   array<int, array{0: int, 1: string}>        $responses  The responses (query ID, response)
     *
     * @return  string  The voter ID
     */
    public static function addVote(MetaRecord $poll, array $responses): string
    {
        $poll_id = $poll->intField('post_id');
        $ident   = My::userIdent();
        $voter   = $ident === My::IDENT_COOKIE ? 'id:' . bin2hex(random_bytes(16)) : self::getIpHash();

        $cur = self::openCursor();
        foreach ($responses as $response) {
            $cur->clean();
            $cur->setField('post_id', $poll_id);
            $cur->setField('option_type', My::TYPE_RESPONSE);
            $cur->setField('option_lang', $poll->strField('post_lang'));
            $cur->setField('option_meta', (string) $response[0]);
            $cur->setField('option_title', $voter);
            $cur->setField('option_content', $response[1]);
            $cur->setField('option_selected', 0);
            self::addOption($cur);
        }

        if ($ident !== My::IDENT_IP) {
            $votes   = self::getCookieVotes();
            $votes[] = $poll_id;
            setcookie(My::COOKIE_NAME, implode(',', array_unique($votes)), [
                'expires'  => time() + 365 * 24 * 3600,
                'path'     => '/',
                'secure'   => str_starts_with(App::blog()->url(), 'https:'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        return $voter;
    }
    //@}

    /// @name Links between entries and polls
    //@{
    /**
     * Get polls IDs of an entry.
     *
     * @param   int     $post_id    The entry ID
     *
     * @return  array<int, int>
     */
    public static function getPollsOfPost(int $post_id): array
    {
        $rs = self::getOptions([
            'option_type' => My::TYPE_POST,
            'post_id'     => $post_id,
        ]);

        $res = [];
        while ($rs->fetch()) {
            $id = (int) $rs->strField('option_meta');
            if ($id > 0) {
                $res[] = $id;
            }
        }

        return array_values(array_unique($res));
    }

    /**
     * Get entries IDs of a poll.
     *
     * @param   int     $poll_id    The poll ID
     *
     * @return  array<int, int>
     */
    public static function getPostsOfPoll(int $poll_id): array
    {
        $rs = self::getOptions([
            'option_type' => My::TYPE_POST,
            'option_meta' => (string) $poll_id,
        ]);

        $res = [];
        while ($rs->fetch()) {
            $res[] = $rs->intField('post_id');
        }

        return array_values(array_unique($res));
    }

    /**
     * Link a poll to an entry.
     *
     * @param   int     $post_id    The entry ID
     * @param   int     $poll_id    The poll ID
     */
    public static function linkPoll(int $post_id, int $poll_id): void
    {
        if ($post_id < 1 || $poll_id < 1 || in_array($poll_id, self::getPollsOfPost($post_id), true)) {
            return;
        }

        $cur = self::openCursor();
        $cur->setField('post_id', $post_id);
        $cur->setField('option_type', My::TYPE_POST);
        $cur->setField('option_meta', (string) $poll_id);
        $cur->setField('option_position', self::nextPosition($post_id, My::TYPE_POST));
        self::addOption($cur);
    }

    /**
     * Unlink poll(s) from an entry.
     *
     * @param   int     $post_id    The entry ID
     * @param   int     $poll_id    The poll ID (or 0 for all polls)
     */
    public static function unlinkPoll(int $post_id, int $poll_id = 0): void
    {
        $sql = new DeleteStatement();
        $sql->from(self::table())
            ->where('post_id = ' . $post_id)
            ->and('option_type = ' . $sql->quote(My::TYPE_POST));

        if ($poll_id !== 0) {
            $sql->and('option_meta = ' . $sql->quote((string) $poll_id));
        }

        $sql->delete();

        App::blog()->triggerBlog();
    }
    //@}

    /**
     * Get a list of integers from a parameter.
     *
     * @return  array<int, int>
     */
    private static function ints(mixed $value): array
    {
        $value = is_array($value) ? $value : [$value];

        return array_values(array_map(intval(...), array_filter($value, is_numeric(...))));
    }

    /**
     * Get a list of strings from a parameter.
     *
     * @return  array<int, string>
     */
    private static function strings(mixed $value): array
    {
        $value = is_array($value) ? $value : [$value];

        return array_values(array_map(fn ($v): string => (string) $v, array_filter($value, fn ($v): bool => is_string($v) || is_int($v))));
    }
}
