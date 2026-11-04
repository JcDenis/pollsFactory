<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Plugin\activityReport\Action;
use Dotclear\Plugin\activityReport\ActivityReport;
use Dotclear\Plugin\activityReport\Group;

/**
 * @brief       pollsFactory plugin activityReport class.
 * @ingroup     pollsFactory
 *
 * Add polls actions to the plugin activity report.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ActivityReportAction
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(true);
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        $group = new Group(My::id(), My::name());

        # from BEHAVIOR publicAfterPollsFactoryVote in pollsFactory/src/FrontendUrl.php
        $group->add(new Action(
            'vote',
            __('New vote'),
            __('New vote on poll called "%s"'),
            'publicAfterPollsFactoryVote',
            function (MetaRecord $poll, string $voter): void {
                ActivityReport::instance()->addLog(My::id(), 'vote', [$poll->strField('post_title')]);
            }
        ));

        # from BEHAVIOR adminAfterPollsFactoryCreate in pollsFactory/src/ManagePoll.php
        $group->add(new Action(
            'create',
            __('Poll creation'),
            __('Poll called "%s" was created by "%s"'),
            'adminAfterPollsFactoryCreate',
            function (Cursor $cur, int $poll_id): void {
                $title = $cur->getField('post_title');
                $user  = App::auth()->getInfo('user_cn');
                ActivityReport::instance()->addLog(My::id(), 'create', [is_string($title) ? $title : '', is_string($user) ? $user : '']);
            }
        ));

        ActivityReport::instance()->groups->add($group);

        return true;
    }
}
