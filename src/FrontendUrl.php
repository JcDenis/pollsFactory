<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * @brief       pollsFactory frontend URL handler.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class FrontendUrl
{
    /**
     * Maximum length of a text response.
     *
     * @var     int     TEXT_MAX_LENGTH
     */
    public const TEXT_MAX_LENGTH = 2000;

    /**
     * Poll page and vote.
     */
    public static function pollPage(?string $args): void
    {
        $args = (string) $args;
        if ($args === '' || !App::frontend()->context()->__get('preview') && !My::isActive()) {
            App::url()::p404();
        }

        if ($args === 'vote') {
            self::vote();

            return;
        }

        $polls = Polls::getPolls(['post_url' => $args]);
        if ($polls->isEmpty()) {
            App::url()::p404();
        }

        App::frontend()->context()->__set('posts', $polls);
        App::url()::serveDocument('pollsfactory.html');
    }

    /**
     * Poll page preview.
     */
    public static function pollPreview(?string $args): void
    {
        if (!preg_match('#^(.+?)/([0-9a-z]{40})/(.+?)$#', (string) $args, $m)) {
            // The specified Preview URL is malformed.
            App::url()::p404();
        }

        if (!App::auth()->checkUser($m[1], null, $m[2])) {
            // The user has no access to the poll.
            App::url()::p404();
        }

        App::frontend()->context()->__set('preview', true);
        if (App::config()->adminUrl() !== '') {
            App::frontend()->context()->__set('xframeoption', App::config()->adminUrl());
        }

        self::pollPage($m[3]);
    }

    /**
     * Record a vote and go back to the voter page.
     */
    private static function vote(): void
    {
        $poll_id = is_numeric($_POST['poll'] ?? null) ? (int) $_POST['poll'] : 0;
        $poll    = Polls::getPoll($poll_id, true);
        $answers = is_array($_POST['pollquery'] ?? null) ? $_POST['pollquery'] : [];

        if ($poll->isEmpty() || $poll->intField('post_open_tb') !== 1 || $answers === []) {
            App::url()::p404();
        }

        // Only redirect to blog pages
        $redir = is_string($_POST['redir'] ?? null) ? $_POST['redir'] : '';
        if (!str_starts_with($redir, App::blog()->url())) {
            $redir = Render::url($poll);
        }
        $redir = preg_replace('/#.*$/', '', $redir) . '#poll-' . $poll_id;

        if (Polls::hasVoted($poll_id)) {
            Http::redirect($redir);
        }

        // Valid selections by query
        $choices = [];
        $rs      = Polls::getSelections($poll_id);
        while ($rs->fetch()) {
            $choices[(int) $rs->strField('option_meta')][] = $rs->intField('option_id');
        }

        // Parse responses
        $responses = [];
        $queries   = Polls::getQueries($poll_id);
        while ($queries->fetch()) {
            $query_id = $queries->intField('option_id');
            $type     = $queries->strField('option_meta');
            $answer   = $answers[$query_id] ?? null;

            if (My::isChoiceType($type)) {
                $values = is_array($answer) ? $answer : [$answer];
                if ($type !== 'checkbox') {
                    $values = array_slice($values, 0, 1);
                }
                foreach ($values as $value) {
                    if (is_numeric($value) && in_array((int) $value, $choices[$query_id] ?? [], true)) {
                        $responses[] = [$query_id, (string) (int) $value];
                    }
                }
            } elseif (in_array($type, My::TEXT_TYPES, true) && is_string($answer) && trim($answer) !== '') {
                $responses[] = [$query_id, mb_substr(trim($answer), 0, $type === 'field' ? 255 : self::TEXT_MAX_LENGTH)];
            }
        }

        if ($responses !== []) {
            try {
                # --BEHAVIOR-- publicBeforePollsFactoryVote -- MetaRecord
                App::behavior()->callBehavior('publicBeforePollsFactoryVote', $poll);

                $voter = Polls::addVote($poll, $responses);

                # --BEHAVIOR-- publicAfterPollsFactoryVote -- MetaRecord, string
                App::behavior()->callBehavior('publicAfterPollsFactoryVote', $poll, $voter);
            } catch (Exception) {
            }
        }

        Http::redirect($redir);
    }
}
