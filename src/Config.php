<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Core\Backend\ModulesList;
use Dotclear\Core\Backend\Notices;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

/**
 * @brief       pollsFactory configuration class.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Config
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::CONFIG));
    }

    /**
     * Polls places combo.
     *
     * @return  array<string, string>
     */
    private static function placeCombo(): array
    {
        return [
            __('hide')           => '',
            __('before content') => 'before',
            __('after content')  => 'after',
        ];
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (empty($_POST['save'])) {
            return true;
        }

        $str = fn (string $name): string => isset($_POST[My::id() . $name]) && is_string($_POST[My::id() . $name]) ? $_POST[My::id() . $name] : '';

        try {
            $s = My::settings();
            $s->put('active', !empty($_POST[My::id() . 'active']), 'boolean');
            $s->put('user_ident', in_array($str('user_ident'), My::userIdentCombo(), true) ? (int) $str('user_ident') : My::IDENT_COOKIE, 'integer');
            $s->put('public_show', !empty($_POST[My::id() . 'public_show']), 'boolean');
            $s->put('public_bars', !empty($_POST[My::id() . 'public_bars']), 'boolean');
            $s->put('public_full', !empty($_POST[My::id() . 'public_full']), 'boolean');
            $s->put('public_place', in_array($str('public_place'), self::placeCombo(), true) ? $str('public_place') : '', 'string');

            $types = isset($_POST[My::id() . 'public_tpltypes']) && is_array($_POST[My::id() . 'public_tpltypes']) ? $_POST[My::id() . 'public_tpltypes'] : [];
            $s->put('public_tpltypes', array_values(array_intersect(My::urlTypesCombo(), array_filter($types, is_string(...)))), 'array');

            App::blog()->triggerBlog();

            Notices::addSuccessNotice(__('Configuration has been successfully updated.'));
            App::backend()->url()->redirect('admin.plugins', [
                'module' => My::id(),
                'conf'   => '1',
                'redir'  => !(App::backend()->__get('list') instanceof ModulesList) ? '' : App::backend()->__get('list')->getRedir(),
            ]);
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return true;
    }

    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        $s     = My::settings();
        $types = [];
        foreach (My::urlTypesCombo() as $name => $type) {
            $types[] = (new Para())->items([
                (new Checkbox([My::id() . 'public_tpltypes[]', My::id() . 'public_tpltypes_' . $type], in_array($type, My::tplTypes(), true)))
                    ->value($type)
                    ->label(new Label($name, Label::IL_FT)),
            ]);
        }

        echo
        (new Fieldset())
            ->legend(new Legend(__('Activation')))
            ->items([
                (new Para())->items([
                    (new Checkbox(My::id() . 'active', My::isActive()))
                        ->value(1)
                        ->label(new Label(__('Enable polls on this blog'), Label::IL_FT)),
                ]),
            ])
            ->render() .
        (new Fieldset())
            ->legend(new Legend(__('Votes')))
            ->items([
                (new Para())->items([
                    (new Select(My::id() . 'user_ident'))
                        ->items(My::userIdentCombo())
                        ->default((string) My::userIdent())
                        ->label(new Label(__('Identify voters by:'), Label::OL_TF)),
                ]),
                (new Note())
                    ->class('form-note')
                    ->text(__('IP addresses are not stored, only a hash of them.')),
                (new Para())->items([
                    (new Checkbox(My::id() . 'public_show', (bool) $s->getBool('public_show', false)))
                        ->value(1)
                        ->label(new Label(__('Show results once visitor has voted (else results are shown when votes are closed)'), Label::IL_FT)),
                ]),
                (new Para())->items([
                    (new Checkbox(My::id() . 'public_bars', (bool) $s->getBool('public_bars', false)))
                        ->value(1)
                        ->label(new Label(__('Use bars for results'), Label::IL_FT)),
                ]),
            ])
            ->render() .
        (new Fieldset())
            ->legend(new Legend(__('Polls of entries')))
            ->items([
                (new Para())->items([
                    (new Select(My::id() . 'public_place'))
                        ->items(self::placeCombo())
                        ->default(My::publicPlace())
                        ->label(new Label(__('Place of polls in entries:'), Label::OL_TF)),
                ]),
                (new Para())->items([
                    (new Checkbox(My::id() . 'public_full', (bool) $s->getBool('public_full', false)))
                        ->value(1)
                        ->label(new Label(__('Show full polls (else only links to polls pages)'), Label::IL_FT)),
                ]),
                (new Text('p', __('Show polls of entries on these pages:'))),
                ... $types,
                (new Note())
                    ->class('form-note')
                    ->text(__('Polls can also be displayed with widget "Polls: polls of an entry" or template tag {{tpl:PollsOfEntry}}.')),
            ])
            ->render();
    }
}
