<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Listing\Listing;
use Dotclear\Core\Backend\Listing\Pager;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Caption;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Component;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Img;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Set;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Tbody;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Thead;
use Dotclear\Helper\Html\Form\Timestamp;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Stack\Status;

/**
 * @brief       pollsFactory polls list.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ListingPolls extends Listing
{
    /**
     * Display polls list.
     *
     * @param   int     $page           The current page
     * @param   int     $nb_per_page    The number of polls per page
     * @param   string  $enclose_block  The enclose block (with %s)
     * @param   bool    $filter         The list is filtered
     */
    public function display(int $page, int $nb_per_page, string $enclose_block = '', bool $filter = false): void
    {
        if ($this->rs->isEmpty()) {
            echo (new Note())
                ->class('info')
                ->text($filter ? __('No poll matches the filter') : __('No poll'))
                ->render();

            return;
        }

        $pager = new Pager($page, $this->rs_count, $nb_per_page, 10);

        /**
         * @var ArrayObject<string, Component>
         */
        $cols = new ArrayObject([
            'title' => (new Th())
                ->scope('col')
                ->colspan(2)
                ->class('first')
                ->text(__('Title')),
            'votes' => (new Th())
                ->scope('col')
                ->text(__('Votes')),
            'voters' => (new Th())
                ->scope('col')
                ->text(__('Voters')),
            'entries' => (new Th())
                ->scope('col')
                ->text(__('Entries')),
            'date' => (new Th())
                ->scope('col')
                ->text(__('Date')),
            'author' => (new Th())
                ->scope('col')
                ->text(__('Author')),
            'status' => (new Th())
                ->scope('col')
                ->text(__('Status')),
        ]);

        $this->userColumns(My::id(), $cols);

        $selected = isset($_POST['entries']) && is_array($_POST['entries']) ? $_POST['entries'] : [];
        $lines    = [];
        while ($this->rs->fetch()) {
            $lines[] = $this->pollLine(in_array((string) $this->rs->intField('post_id'), $selected, true));
        }

        $table = (new Div())
            ->class('table-outer')
            ->items([
                (new Table())
                    ->class('maximal')
                    ->caption(new Caption(
                        $filter ?
                        sprintf(__('List of %s polls matching the filter.'), $this->rs_count) :
                        sprintf(__('List of polls (%s)'), $this->rs_count)
                    ))
                    ->items([
                        (new Thead())
                            ->rows([
                                (new Tr())
                                    ->items(iterator_to_array($cols)),
                            ]),
                        (new Tbody())
                            ->rows($lines),
                    ]),
            ])
            ->render();

        echo
        $pager->getLinks() .
        ($enclose_block !== '' ? sprintf($enclose_block, $table) : $table) .
        (new Note())
            ->class('info')
            ->text(
                __('Legend: ') .
                (new Set())
                    ->separator(' - ')
                    ->items([
                        ... array_map(fn (Status $k): Img|Set|Text => App::status()->post()->image($k->id(), true), App::status()->post()->dump(false)),
                        self::getRowImage(__('Selected'), 'images/selected.svg', 'selected', true),
                        self::getRowImage(__('Votes opened'), 'images/check-on.svg', 'check-on', true),
                        self::getRowImage(__('Votes closed'), 'images/check-off.svg', 'check-off', true),
                    ])
                    ->render()
            )
            ->render() .
        $pager->getLinks();
    }

    /**
     * Get poll line.
     *
     * @param   bool    $checked    The line is checked
     */
    private function pollLine(bool $checked): Tr
    {
        $post_id = $this->rs->intField('post_id');
        $user_tz = is_string($user_tz = App::auth()->getInfo('user_tz')) ? $user_tz : 'UTC';
        $opened  = $this->rs->intField('post_open_tb') === 1;

        $classes = ['line', 'sts-' . App::status()->post()->id($this->rs->intField('post_status'))];
        if (App::status()->post()->isRestricted($this->rs->intField('post_status'))) {
            $classes[] = 'offline';
        }

        $status = [App::status()->post()->image($this->rs->intField('post_status'))];
        if ($this->rs->boolField('post_selected')) {
            $status[] = self::getRowImage(__('Selected'), 'images/selected.svg', 'selected');
        }
        $status[] = $opened ?
            self::getRowImage(__('Votes opened'), 'images/check-on.svg', 'check-on') :
            self::getRowImage(__('Votes closed'), 'images/check-off.svg', 'check-off');

        $voters  = Polls::countVoters($post_id);
        $entries = count(Polls::getPostsOfPoll($post_id));

        /**
         * @var ArrayObject<string, Component>
         */
        $cols = new ArrayObject([
            'check' => (new Td())
                ->class('nowrap')
                ->items([
                    (new Checkbox(['entries[]'], $checked))
                        ->value($post_id)
                        ->disabled(!$this->rs->isEditable())
                        ->title(__('Select this poll')),
                ]),
            'title' => (new Td())
                ->class('maximal')
                ->items([
                    (new Link())
                        ->href(App::postTypes()->getPostAdminURL(My::POST_TYPE, $post_id))
                        ->text(Html::escapeHTML(trim(Html::clean($this->rs->strField('post_title'))))),
                ]),
            'votes' => (new Td())
                ->class('nowrap')
                ->text($opened ? __('opened') : __('closed')),
            'voters' => (new Td())
                ->class(['nowrap', 'count'])
                ->items([
                    $voters > 0 ?
                        (new Link())
                            ->href(My::manageUrl(['part' => 'results', 'id' => $post_id]))
                            ->title(__('See results'))
                            ->text((string) $voters) :
                        (new Text(null, '0')),
                ]),
            'entries' => (new Td())
                ->class(['nowrap', 'count'])
                ->text((string) $entries),
            'date' => (new Td())
                ->class(['nowrap', 'count'])
                ->items([
                    (new Timestamp(Date::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->strField('post_dt'))))
                        ->datetime(Date::iso8601((int) strtotime($this->rs->strField('post_dt')), $user_tz)),
                ]),
            'author' => (new Td())
                ->class('nowrap')
                ->text(Html::escapeHTML($this->rs->strField('user_id'))),
            'status' => (new Td())
                ->class(['nowrap', 'status'])
                ->items([
                    (new Set())
                        ->separator(' ')
                        ->items($status),
                ]),
        ]);

        $this->userColumns(My::id(), $cols, true);

        return (new Tr())
            ->id('p' . $post_id)
            ->class($classes)
            ->items(iterator_to_array($cols));
    }
}
