<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Tbody;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Thead;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

/**
 * @brief       pollsFactory backend poll results.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ManageResults
{
    use TraitProcess;

    private static ?MetaRecord $poll = null;

    public static function init(): bool
    {
        if (!My::checkContext(My::MANAGE)) {
            return self::status(false);
        }

        $poll_id = isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
        $poll    = Polls::getPoll($poll_id, true);
        if ($poll->isEmpty()) {
            Notices::addErrorNotice(__('This poll does not exist.'));
            My::redirect();
        }
        self::$poll = $poll;

        return self::status(true);
    }

    public static function process(): bool
    {
        if (!self::status() || !self::$poll instanceof MetaRecord || $_POST === [] || !self::$poll->isEditable()) {
            return self::status();
        }

        $poll_id = self::$poll->intField('post_id');

        try {
            // Select text responses for public display
            if (!empty($_POST['responses_save'])) {
                $shown    = is_array($_POST['responses'] ?? null) ? $_POST['responses'] : [];
                $selected = is_array($_POST['selected'] ?? null) ? array_map(intval(...), array_filter($_POST['selected'], is_numeric(...))) : [];
                foreach ($shown as $id) {
                    if (is_numeric($id)) {
                        Polls::setResponseSelected($poll_id, (int) $id, in_array((int) $id, $selected, true));
                    }
                }

                Notices::addSuccessNotice(__('Selected responses have been updated.'));
                My::redirect(['part' => 'results', 'id' => $poll_id]);
            }

            // Delete voters responses
            if (!empty($_POST['voters_delete'])) {
                $voters = is_array($_POST['voters'] ?? null) ? array_filter($_POST['voters'], is_string(...)) : [];
                foreach ($voters as $voter) {
                    Polls::delVoter($poll_id, $voter);
                }

                Notices::addSuccessNotice(__('Responses of selected voters have been deleted.'));
                My::redirect(['part' => 'results', 'id' => $poll_id]);
            }

            // Open or close votes
            if (!empty($_POST['votes_open']) || !empty($_POST['votes_close'])) {
                Polls::setOpened($poll_id, !empty($_POST['votes_open']));

                Notices::addSuccessNotice(!empty($_POST['votes_open']) ? __('Votes have been opened.') : __('Votes have been closed.'));
                My::redirect(['part' => 'results', 'id' => $poll_id]);
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return true;
    }

    public static function render(): void
    {
        if (!self::status() || !self::$poll instanceof MetaRecord) {
            return;
        }

        $poll    = self::$poll;
        $poll_id = $poll->intField('post_id');
        $voters  = Polls::countVoters($poll_id);
        $opened  = $poll->intField('post_open_tb') === 1;

        Page::openModule(
            __('Results') . ' - ' . My::name(),
            Page::jsLoad('js/_posts_list.js') .
            My::cssLoad('backend')
        );

        echo
        Page::breadcrumb([
            Html::escapeHTML(App::blog()->name())                                              => '',
            My::name()                                                                         => My::manageUrl(),
            '&ldquo;' . Html::escapeHTML(trim(Html::clean($poll->strField('post_title')))) . '&rdquo;' => My::manageUrl(['part' => 'poll', 'id' => $poll_id]),
            __('Results')                                                                      => '',
        ]) .
        Notices::getNotices();

        echo (new Form('poll-votes-form'))
            ->method('post')
            ->action(My::manageUrl(['part' => 'results', 'id' => $poll_id]))
            ->fields([
                (new Para())->items([
                    (new Text(null, ($opened ? __('Votes are opened.') : __('Votes are closed.')) . ' ' . match ($voters) {
                        0       => __('Nobody has participated.'),
                        1       => __('One person has participated.'),
                        default => sprintf(__('%d people have participated.'), $voters),
                    } . ' ')),
                    $opened ?
                        (new Submit(['votes_close'], __('Close votes'))) :
                        (new Submit(['votes_open'], __('Open votes'))),
                    (new Link())
                        ->href(My::manageUrl(['part' => 'poll', 'id' => $poll_id]))
                        ->text(' ' . __('Edit poll')),
                    App::nonce()->formNonce(),
                ]),
            ])
            ->render();

        if ($voters === 0) {
            Page::closeModule();

            return;
        }

        // Count responses
        $counts = [];
        $texts  = [];
        $rs     = Polls::getResponses($poll_id);
        while ($rs->fetch()) {
            $query_id                    = (int) $rs->strField('option_meta');
            $content                     = $rs->strField('option_content');
            $counts[$query_id]['_' . $content] = ($counts[$query_id]['_' . $content] ?? 0) + 1;
            $texts[$query_id][]          = [
                'id'       => $rs->intField('option_id'),
                'text'     => $content,
                'selected' => $rs->intField('option_selected') === 1,
                'dt'       => $rs->strField('option_creadt'),
            ];
        }

        $selections = [];
        $rs         = Polls::getSelections($poll_id);
        while ($rs->fetch()) {
            $selections[(int) $rs->strField('option_meta')][$rs->intField('option_id')] = $rs->strField('option_title');
        }

        $items   = [];
        $queries = Polls::getQueries($poll_id);
        while ($queries->fetch()) {
            $query_id = $queries->intField('option_id');
            $type     = $queries->strField('option_meta');

            if (My::isChoiceType($type)) {
                $rows = [];
                foreach ($selections[$query_id] ?? [] as $selection_id => $title) {
                    $nb      = $counts[$query_id]['_' . $selection_id] ?? 0;
                    $percent = (int) round($nb / $voters * 100);
                    $rows[]  = (new Tr())->items([
                        (new Td())->class('maximal')->text(Html::escapeHTML($title)),
                        (new Td())->class('nowrap')->text('<progress class="poll-bar" max="100" value="' . $percent . '">' . $percent . '%</progress>'),
                        (new Td())->class(['nowrap', 'count'])->text($percent . '%'),
                        (new Td())->class(['nowrap', 'count'])->text((string) $nb),
                    ]);
                }
                $items[] = (new Div())->class('fieldset')->items([
                    (new Text('h4', Html::escapeHTML($queries->strField('option_title')))),
                    (new Table())->class('maximal')->items([
                        (new Thead())->rows([(new Tr())->items([
                            (new Th())->text(__('Choice')),
                            (new Th())->text(''),
                            (new Th())->text('%'),
                            (new Th())->text(__('Votes')),
                        ])]),
                        (new Tbody())->rows($rows),
                    ]),
                ]);
            } else {
                $rows = [];
                foreach ($texts[$query_id] ?? [] as $text) {
                    if ($text['text'] === '') {
                        continue;
                    }
                    $rows[] = (new Tr())->items([
                        (new Td())->class('nowrap')->items([
                            (new Checkbox(['selected[]', 'selected_' . $text['id']], $text['selected']))
                                ->value($text['id'])
                                ->title(__('Show on public side')),
                            (new Hidden(['responses[]'], (string) $text['id'])),
                        ]),
                        (new Td())->class('maximal')->text(nl2br(Html::escapeHTML($text['text']))),
                        (new Td())->class(['nowrap', 'count'])->text(Date::dt2str(__('%Y-%m-%d %H:%M'), $text['dt'])),
                    ]);
                }
                $items[] = (new Div())->class('fieldset')->items([
                    (new Text('h4', Html::escapeHTML($queries->strField('option_title')))),
                    $rows === [] ?
                        (new Note())->text(__('No response')) :
                        (new Table())->class('maximal')->items([
                            (new Thead())->rows([(new Tr())->items([
                                (new Th())->text(__('Public')),
                                (new Th())->text(__('Response')),
                                (new Th())->text(__('Date')),
                            ])]),
                            (new Tbody())->rows($rows),
                        ]),
                ]);
            }
        }

        echo (new Form('poll-responses-form'))
            ->method('post')
            ->action(My::manageUrl(['part' => 'results', 'id' => $poll_id]))
            ->fields([
                ... $items,
                (new Note())
                    ->class('form-note')
                    ->text(__('Only checked text responses are displayed on public side.')),
                (new Para())->class('form-buttons')->items([
                    (new Submit(['responses_save'], __('Save selected responses'))),
                    App::nonce()->formNonce(),
                ]),
            ])
            ->render();

        // Voters
        $rows = [];
        foreach (Polls::getVoters($poll_id) as $voter => $dt) {
            $rows[] = (new Tr())->class('line')->items([
                (new Td())->class('nowrap')->items([
                    (new Checkbox(['voters[]'], false))
                        ->value(Html::escapeHTML($voter))
                        ->title(__('Select this voter')),
                ]),
                (new Td())->class('maximal')->text(Html::escapeHTML(substr($voter, 0, 12)) . '&hellip;'),
                (new Td())->class(['nowrap', 'count'])->text(Date::dt2str(__('%Y-%m-%d %H:%M'), $dt)),
            ]);
        }

        echo (new Form('poll-voters-form'))
            ->method('post')
            ->action(My::manageUrl(['part' => 'results', 'id' => $poll_id]))
            ->fields([
                (new Text('h3', __('Voters'))),
                (new Table())->class('maximal')->items([
                    (new Thead())->rows([(new Tr())->items([
                        (new Th())->text(''),
                        (new Th())->text(__('Voter')),
                        (new Th())->text(__('Date')),
                    ])]),
                    (new Tbody())->rows($rows),
                ]),
                (new Div())->class('two-cols')->items([
                    (new Para())->class(['col', 'checkboxes-helpers']),
                    (new Para())->class(['col', 'right', 'form-buttons'])->items([
                        (new Submit(['voters_delete'], __('Delete responses of selected voters')))->class('delete'),
                        App::nonce()->formNonce(),
                    ]),
                ]),
            ])
            ->render();

        Page::helpBlock(My::id());
        Page::closeModule();
    }
}
