<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory\ClaudetteMcp;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Plugin\ClaudetteMcp\Tool\PostTool;
use Dotclear\Plugin\pollsFactory\My;
use Dotclear\Plugin\pollsFactory\Polls;

/**
 * @brief       pollsFactory tools helper for the plugin ClaudetteMcp.
 * @ingroup     pollsFactory
 *
 * ClaudetteMcp connects an AI assistant to the blog: these tools let the assistant read the polls and their results.
 * Read only: polls are not opened to the entries tools, a poll is made of options the entries tools do not know.
 * Voters are never identified.
 * These classes are only loaded by ClaudetteMcp, which is not required by pollsFactory.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
abstract class PollTool extends PostTool
{
    /**
     * Family of tools for the keys of ClaudetteMcp (string, not constant: older ClaudetteMcp versions have none).
     */
    public function family(): string
    {
        return 'stats';
    }

    /**
     * Like in backend: the plugin must be active on the blog, the user must be able to write entries.
     */
    public function available(): bool
    {
        return My::isActive() && $this->canWrite();
    }

    /**
     * Get polls the user can see.
     *
     * Same rules as entries: published polls for everyone, others for their author or a content administrator.
     *
     * @param   array<string, mixed>    $params
     */
    protected function polls(string $status, array $params = [], bool $count_only = false): MetaRecord
    {
        return App::blog()->getPosts(array_merge($this->visibility($status, My::POST_TYPE), $params), $count_only);
    }

    /**
     * Describe a poll.
     *
     * @return  array<string, mixed>
     */
    protected function describePoll(MetaRecord $rs): array
    {
        $status = array_search($rs->intField('post_status'), $this->statuses(), true);

        return [
            'id'      => $rs->intField('post_id'),
            'title'   => $rs->strField('post_title'),
            'status'  => $status === false ? 'unknown' : $status,
            'opened'  => $rs->intField('post_open_tb') === 1,
            'voters'  => Polls::countVoters($rs->intField('post_id')),
            'date'    => $rs->strField('post_dt'),
            'author'  => $rs->strField('user_id'),
            'entries' => Polls::getPostsOfPoll($rs->intField('post_id')),
        ];
    }
}
