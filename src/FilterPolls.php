<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Filter\Filters;
use Dotclear\Core\Backend\Filter\FiltersLibrary;
use Dotclear\Helper\Stack\Filter;
use Exception;

/**
 * @brief       pollsFactory polls list filters.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class FilterPolls extends Filters
{
    public function __construct()
    {
        parent::__construct(My::id());

        $filters = new ArrayObject(array_filter([
            FiltersLibrary::getPageFilter(),
            FiltersLibrary::getSearchFilter(),
            $this->getUserFilter(),
            App::status()->post()->filter(),
            $this->getSelectedFilter(),
            $this->getOpenedFilter(),
        ]));

        # --BEHAVIOR-- adminPollsFactoryFilter -- ArrayObject
        App::behavior()->callBehavior('adminPollsFactoryFilter', $filters);

        $this->add($filters->getArrayCopy());
    }

    public function getUserFilter(): ?Filter
    {
        try {
            $users = App::blog()->getPostsUsers(My::POST_TYPE);
            if ($users->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());

            return null;
        }

        $combo = App::backend()->combos()->getUsersCombo($users);
        App::lexical()->lexicalKeySort($combo, App::lexical()::ADMIN_LOCALE);

        return (new Filter('user_id'))
            ->param()
            ->title(__('Author:'))
            ->options(array_merge(['-' => ''], $combo))
            ->prime(true);
    }

    public function getSelectedFilter(): Filter
    {
        return (new Filter('selected'))
            ->param('post_selected')
            ->title(__('Selected:'))
            ->options([
                '-'                => '',
                __('Selected')     => '1',
                __('Not selected') => '0',
            ]);
    }

    public function getOpenedFilter(): Filter
    {
        return (new Filter('opened'))
            ->param('sql', fn ($f): string => is_array($f) && in_array($f[0] ?? '', ['0', '1'], true) ? 'AND post_open_tb = ' . $f[0] . ' ' : '')
            ->title(__('Votes:'))
            ->options([
                '-'          => '',
                __('Opened') => '1',
                __('Closed') => '0',
            ])
            ->prime(true);
    }
}
