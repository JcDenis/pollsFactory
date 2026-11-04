<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Option;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Radio;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Textarea;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;

/**
 * @brief       pollsFactory public rendering.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Render
{
    /**
     * Rendered forms counter (to get unique HTML ids).
     */
    private static int $count = 0;

    /**
     * Already rendered polls (to get unique HTML ids).
     *
     * @var     array<int, int>     $rendered
     */
    private static array $rendered = [];

    /**
     * Get unique poll block ID.
     *
     * @param   int     $poll_id    The poll ID
     */
    private static function blockId(int $poll_id): ?string
    {
        if (in_array($poll_id, self::$rendered, true)) {
            return null;
        }
        self::$rendered[] = $poll_id;

        return 'poll-' . $poll_id;
    }

    /**
     * Get poll block container.
     *
     * @param   int                 $poll_id    The poll ID
     * @param   array<int, string>  $class      The block classes
     */
    private static function block(int $poll_id, array $class): Div
    {
        $div = (new Div())->class($class);
        $id  = self::blockId($poll_id);

        return $id === null ? $div : $div->id($id);
    }
    /**
     * Render a poll (form, results or waiting message).
     *
     * @param   int     $poll_id        The poll ID
     * @param   bool    $show_title     Show poll title
     * @param   bool    $show_desc      Show poll description
     * @param   null|bool   $bars       Use bars for results (null to use blog setting)
     */
    public static function poll(int $poll_id, bool $show_title = false, bool $show_desc = false, ?bool $bars = null): string
    {
        $poll = Polls::getPoll($poll_id);
        if ($poll->isEmpty()) {
            return '';
        }

        $bars ??= (bool) My::settings()->getBool('public_bars', false);
        $head   = self::head($poll, $show_title, $show_desc);
        $opened = $poll->intField('post_open_tb') === 1;

        if (self::isPreview() || $opened && !Polls::hasVoted($poll_id)) {
            return self::form($poll, $head);
        }

        if (!$opened || My::settings()->getBool('public_show', false)) {
            return self::results($poll, $head, $bars);
        }

        return self::block($poll_id, ['pollsfactory', 'poll-wait'])
            ->items([
                (new Text(null, $head)),
                (new Para())
                    ->class('poll-info')
                    ->items([
                        (new Text(null, __('You have already participated in this poll.') . '<br>' . __('Please wait for the end of this poll to see results.'))),
                    ]),
            ])
            ->render();
    }

    /**
     * Get public URL of a poll.
     *
     * @param   MetaRecord  $poll   The poll
     */
    public static function url(MetaRecord $poll): string
    {
        return App::blog()->url() . App::url()->getURLFor('pollsfactory_page', Html::sanitizeURL($poll->strField('post_url')));
    }

    /**
     * Get a list of links to polls.
     *
     * @param   array<int, int>     $polls_ids  The polls IDs
     */
    public static function links(array $polls_ids): string
    {
        if ($polls_ids === []) {
            return '';
        }

        $rs = Polls::getPolls([
            'post_id'    => $polls_ids,
            'no_content' => true,
        ]);

        $items = [];
        while ($rs->fetch()) {
            $items[] = '<li><a href="' . self::url($rs) . '">' . Html::escapeHTML($rs->strField('post_title')) . '</a></li>';
        }

        return $items === [] ? '' : '<ul class="polls-list">' . implode('', $items) . '</ul>';
    }

    /**
     * Check if we are in preview mode.
     */
    private static function isPreview(): bool
    {
        return App::task()->checkContext('FRONTEND') && App::frontend()->context()->__get('preview') === true;
    }

    /**
     * Poll title and description.
     *
     * @param   MetaRecord  $poll           The poll
     * @param   bool        $show_title     Show poll title
     * @param   bool        $show_desc      Show poll description
     */
    private static function head(MetaRecord $poll, bool $show_title, bool $show_desc): string
    {
        $res = '';
        if ($show_title) {
            $res .= '<h3 class="poll-title"><a href="' . self::url($poll) . '">' . Html::escapeHTML($poll->strField('post_title')) . '</a></h3>';
        }
        if ($show_desc && $poll->strField('post_content_xhtml') !== '') {
            $res .= '<div class="poll-desc">' . $poll->strField('post_content_xhtml') . '</div>';
        }

        return $res;
    }

    /**
     * Text to HTML.
     *
     * @param   string  $text   The text
     */
    private static function text(string $text): string
    {
        return nl2br(Html::escapeHTML($text));
    }

    /**
     * Poll form.
     *
     * @param   MetaRecord  $poll   The poll
     * @param   string      $head   The poll title and description
     */
    private static function form(MetaRecord $poll, string $head): string
    {
        $poll_id    = $poll->intField('post_id');
        $queries    = Polls::getQueries($poll_id);
        $selections = self::selections($poll_id);
        $fields     = [];
        $prefix     = 'pf' . (++self::$count) . '-';

        while ($queries->fetch()) {
            $query_id = $queries->intField('option_id');
            $type     = $queries->strField('option_meta');
            $name     = 'pollquery[' . $query_id . ']';
            $items    = [];

            if (My::isChoiceType($type)) {
                $choices = $selections[$query_id] ?? [];
                if ($choices === []) {
                    continue;
                }

                if ($type === 'combo') {
                    $options = [];
                    foreach ($choices as $selection_id => $title) {
                        $options[] = new Option(Html::escapeHTML($title), (string) $selection_id);
                    }
                    $items[] = (new Para())->class('poll-input')->items([
                        (new Select([$name, $prefix . $query_id]))
                            ->class('poll-combo')
                            ->items($options)
                            ->label((new Label(Html::escapeHTML($queries->strField('option_title')), Label::OL_TF))->class('poll-label')),
                    ]);
                } else {
                    foreach ($choices as $selection_id => $title) {
                        $id      = $prefix . $query_id . '-' . $selection_id;
                        $items[] = (new Para())->class('poll-choice')->items([
                            ($type === 'checkbox' ? new Checkbox([$name . '[]', $id]) : new Radio([$name, $id]))
                                ->class('poll-' . $type)
                                ->value($selection_id)
                                ->label(new Label(Html::escapeHTML($title), Label::IL_FT)),
                        ]);
                    }
                }
            } elseif ($type === 'field') {
                $items[] = (new Para())->class('poll-input')->items([
                    (new Input([$name, $prefix . $query_id]))
                        ->class('poll-field')
                        ->size(30)
                        ->maxlength(255)
                        ->label((new Label(Html::escapeHTML($queries->strField('option_title')), Label::OL_TF))->class('poll-label')),
                ]);
            } elseif ($type === 'textarea') {
                $items[] = (new Para())->class('poll-input')->items([
                    (new Textarea([$name, $prefix . $query_id]))
                        ->class('poll-textarea')
                        ->cols(35)
                        ->rows(5)
                        ->label((new Label(Html::escapeHTML($queries->strField('option_title')), Label::OL_TF))->class('poll-label')),
                ]);
            } else {
                continue;
            }

            if ($queries->strField('option_content') !== '') {
                $desc = (new Div())->class('poll-query-desc')->items([(new Text(null, self::text($queries->strField('option_content'))))]);
                in_array($type, ['checkbox', 'radio'], true) ? array_unshift($items, $desc) : $items[] = $desc;
            }

            $fields[] = in_array($type, ['checkbox', 'radio'], true) ?
                (new Fieldset())
                    ->class(['poll-query', 'poll-query-' . $type])
                    ->legend(new Legend(Html::escapeHTML($queries->strField('option_title'))))
                    ->items($items) :
                (new Div())
                    ->class(['poll-query', 'poll-query-' . $type])
                    ->items($items);
        }

        if ($fields === []) {
            return '';
        }

        return self::block($poll_id, ['pollsfactory', 'poll-form'])
            ->items([
                (new Text(null, $head)),
                (new Form($prefix . 'form'))
                    ->method('post')
                    ->action(App::blog()->url() . App::url()->getURLFor('pollsfactory_page', 'vote'))
                    ->fields([
                        ...$fields,
                        (new Para())
                            ->class('poll-submit')
                            ->items([
                                (new Hidden(['poll'], (string) $poll_id)),
                                (new Hidden(['redir'], Html::escapeHTML(Http::getSelfURI()))),
                                (new Submit(['poll_submit_' . $poll_id], __('Vote'))),
                            ]),
                    ]),
            ])
            ->render();
    }

    /**
     * Poll results.
     *
     * @param   MetaRecord  $poll   The poll
     * @param   string      $head   The poll title and description
     * @param   bool        $bars   Use bars
     */
    private static function results(MetaRecord $poll, string $head, bool $bars): string
    {
        $poll_id    = $poll->intField('post_id');
        $voters     = Polls::countVoters($poll_id);
        $selections = self::selections($poll_id);
        $res        = '';

        if ($voters > 0) {
            // Count responses
            $counts = $texts = [];
            $rs     = Polls::getResponses($poll_id);
            while ($rs->fetch()) {
                $query_id = (int) $rs->strField('option_meta');
                $content  = $rs->strField('option_content');
                $counts[$query_id]['_' . $content] = ($counts[$query_id]['_' . $content] ?? 0) + 1;
                if ($rs->intField('option_selected') === 1 && $content !== '') {
                    $texts[$query_id][] = $content;
                }
            }

            $queries = Polls::getQueries($poll_id);
            while ($queries->fetch()) {
                $query_id = $queries->intField('option_id');
                $type     = $queries->strField('option_meta');

                if (My::isChoiceType($type)) {
                    $lines = [];
                    foreach ($selections[$query_id] ?? [] as $selection_id => $title) {
                        $nb      = $counts[$query_id]['_' . $selection_id] ?? 0;
                        $percent = (int) round($nb / $voters * 100);
                        $lines[] = ['nb' => $nb, 'html' => '<li>' .
                            '<span class="poll-label">' . Html::escapeHTML($title) . '</span> ' .
                            ($bars ? '<progress class="poll-bar" max="100" value="' . $percent . '">' . $percent . '%</progress> ' : '') .
                            '<span class="poll-count">' . $percent . '% - ' . self::votes($nb) . '</span>' .
                            '</li>'];
                    }
                    if ($lines === []) {
                        continue;
                    }
                    usort($lines, fn (array $a, array $b): int => $b['nb'] <=> $a['nb']);
                    $res .= '<div class="poll-query"><h4>' . Html::escapeHTML($queries->strField('option_title')) . '</h4>' .
                        '<ul class="poll-results">' . implode('', array_column($lines, 'html')) . '</ul></div>';
                } elseif (!empty($texts[$query_id])) {
                    $res .= '<div class="poll-query"><h4>' . Html::escapeHTML($queries->strField('option_title')) . '</h4>' .
                        '<p class="poll-info">' . __('Some selected responses:') . '</p>';
                    foreach ($texts[$query_id] as $text) {
                        $res .= '<blockquote><p>' . self::text($text) . '</p></blockquote>';
                    }
                    $res .= '</div>';
                }
            }
        }

        $info = $poll->intField('post_open_tb') === 1 ? '' : __('This poll is closed.') . '<br>';
        $info .= match ($voters) {
            0       => __('Nobody has participated.'),
            1       => __('One person has participated.'),
            default => sprintf(__('%d people have participated.'), $voters),
        };

        $id = self::blockId($poll_id);

        return '<div class="pollsfactory poll-result"' . ($id !== null ? ' id="' . $id . '"' : '') . '>' . $head .
            '<p class="poll-info">' . $info . '</p>' . $res . '</div>';
    }

    /**
     * Number of votes text.
     *
     * @param   int     $nb     The number of votes
     */
    private static function votes(int $nb): string
    {
        return match ($nb) {
            0       => __('no vote'),
            1       => __('one vote'),
            default => sprintf(__('%d votes'), $nb),
        };
    }

    /**
     * Get selections of a poll by query.
     *
     * @param   int     $poll_id    The poll ID
     *
     * @return  array<int, array<int, string>>  The selections titles by query and selection IDs
     */
    private static function selections(int $poll_id): array
    {
        $res = [];
        $rs  = Polls::getSelections($poll_id);
        while ($rs->fetch()) {
            $res[(int) $rs->strField('option_meta')][$rs->intField('option_id')] = $rs->strField('option_title');
        }

        return $res;
    }
}
