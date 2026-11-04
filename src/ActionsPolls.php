<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Core\Backend\Action\ActionsPostsDefault;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * @brief       pollsFactory polls list actions.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ActionsPolls extends ActionsPosts
{
    protected bool $use_render = true;

    /**
     * @param   null|string             $uri            The form uri
     * @param   array<string, string>   $redirect_args  The redirection arguments
     */
    public function __construct(?string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        $this->redirect_fields = [
            'user_id', 'status', 'selected', 'opened', 'q', 'sortby', 'order', 'page', 'nb',
        ];
        $this->caller_title = __('Polls');
    }

    public function loadDefaults(): void
    {
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_PUBLISH,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $this->addAction(
                [__('Status') => App::status()->post()->action()],
                ActionsPostsDefault::doChangePostStatus(...)
            );
        }

        $this->addAction(
            [__('Votes') => [
                __('Open votes')  => 'pollsfactory_open',
                __('Close votes') => 'pollsfactory_close',
            ]],
            self::doOpenClose(...)
        );

        $this->addAction(
            [__('Mark') => [
                __('Mark as selected')   => 'selected',
                __('Mark as unselected') => 'unselected',
            ]],
            ActionsPostsDefault::doUpdateSelectedPost(...)
        );

        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_DELETE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $this->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete',
                ]],
                ActionsPostsDefault::doDeletePost(...)
            );
        }

        # --BEHAVIOR-- adminPollsFactoryActions -- ActionsPolls
        App::behavior()->callBehavior('adminPollsFactoryActions', $this);
    }

    public function beginPage(string $breadcrumb = '', string $head = ''): void
    {
        Page::openModule(
            My::name(),
            Page::jsLoad('js/_posts_actions.js') .
            $head
        );
        echo
        $breadcrumb .
        (new Para())->items([
            (new Link())
                ->class('back')
                ->href($this->getRedirection(true))
                ->text(__('Back to polls list')),
        ])->render();
    }

    public function endPage(): void
    {
        Page::closeModule();
    }

    public function error(Exception $e): void
    {
        App::error()->add($e->getMessage());
        $this->beginPage(
            Page::breadcrumb([
                Html::escapeHTML(App::blog()->name()) => '',
                $this->getCallerTitle()               => $this->getRedirection(true),
                __('Polls actions')                   => '',
            ])
        );
        $this->endPage();
    }

    public function process()
    {
        $this->from['post_type'] = My::POST_TYPE;

        return parent::process();
    }

    /**
     * Open or close votes.
     *
     * @param   ActionsPosts                    $ap     The actions instance
     * @param   ArrayObject<string, mixed>      $post   The POST values
     */
    public static function doOpenClose(ActionsPosts $ap, ArrayObject $post): void
    {
        $ids = array_values(array_filter(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $ap->getIDs())));
        if ($ids === []) {
            throw new Exception(__('No poll selected'));
        }

        $open = $ap->getAction() === 'pollsfactory_open';
        foreach ($ids as $id) {
            Polls::setOpened($id, $open);
        }

        Notices::addSuccessNotice($open ? __('Votes have been opened.') : __('Votes have been closed.'));
        $ap->redirect(true);
    }
}
