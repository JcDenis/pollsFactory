<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Component;
use Dotclear\Helper\Html\Form\Datetime;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Img;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Optgroup;
use Dotclear\Helper\Html\Form\Option;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Span;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Tbody;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Textarea;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Thead;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

/**
 * @brief       pollsFactory backend poll edition.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ManagePoll
{
    use TraitProcess;

    private static ?MetaRecord $post = null;
    private static int $post_id      = 0;
    private static int $post_dt      = 0;
    private static string $post_format  = '';
    private static string $post_url     = '';
    private static string $post_lang    = '';
    private static string $post_title   = '';
    private static string $post_content = '';
    private static string $post_content_xhtml = '';
    private static int $post_status     = 0;
    private static bool $post_selected  = false;
    private static bool $post_opened    = true;
    private static bool $can_edit       = false;
    private static bool $can_publish    = false;
    private static bool $can_delete     = false;
    private static bool $has_votes      = false;

    /**
     * @var     array<string, string>   $available_formats
     */
    private static array $available_formats = [];

    /**
     * @var     array<array-key, Optgroup|Option>   $lang_combo
     */
    private static array $lang_combo = [];

    public static function init(): bool
    {
        if (!My::checkContext(My::MANAGE)) {
            return self::status(false);
        }

        $user_tz = is_string($user_tz = App::auth()->getInfo('user_tz')) ? $user_tz : 'UTC';
        Date::setTZ($user_tz);

        self::$post_format = (string) App::auth()->prefs()->get('interface')->getStr('post_format', false);
        self::$post_lang   = is_string($post_lang = App::auth()->getInfo('user_lang')) ? $post_lang : '';
        self::$post_status = is_numeric($post_status = App::auth()->getInfo('user_post_status')) ? (int) $post_status : 0;

        self::$can_edit = App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id());
        self::$can_publish = App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_PUBLISH,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id());

        if (!self::$can_publish) {
            self::$post_status = App::status()->post()::PENDING;
        }

        $available_formats = [];
        foreach (App::formater()->getFormaters() as $formats) {
            foreach ($formats as $format) {
                $available_formats[App::formater()->getFormaterName($format)] = $format;
            }
        }
        self::$available_formats = $available_formats;

        self::$lang_combo = App::backend()->combos()->getLangsCombo(
            App::blog()->getLangs(['order_by' => 'nb_post', 'order' => 'desc']),
            true,
            true
        );

        // Get poll
        $poll_id = isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
        if ($poll_id > 0) {
            $post = Polls::getPoll($poll_id);
            if ($post->isEmpty()) {
                Notices::addErrorNotice(__('This poll does not exist.'));
                My::redirect();
            }

            self::$post               = $post;
            self::$post_id            = $post->intField('post_id');
            self::$post_dt            = (int) strtotime($post->strField('post_dt'));
            self::$post_format        = $post->strField('post_format');
            self::$post_url           = $post->strField('post_url');
            self::$post_lang          = $post->strField('post_lang');
            self::$post_title         = $post->strField('post_title');
            self::$post_content       = $post->strField('post_content');
            self::$post_content_xhtml = $post->strField('post_content_xhtml');
            self::$post_status        = $post->intField('post_status');
            self::$post_selected      = $post->boolField('post_selected');
            self::$post_opened        = $post->intField('post_open_tb') === 1;
            self::$can_edit           = (bool) $post->isEditable();
            self::$can_delete         = (bool) $post->isDeletable();
            self::$has_votes          = Polls::countVoters(self::$post_id) > 0;
        }

        return self::status(true);
    }

    public static function process(): bool
    {
        if (!self::status() || !self::$can_edit || $_POST === []) {
            return self::status();
        }

        try {
            if (!empty($_POST['delete']) && self::$can_delete && self::$post_id > 0) {
                self::deletePoll();
            } elseif (!empty($_POST['save'])) {
                self::savePoll();
            } elseif (self::$post_id > 0) {
                self::processQueries();
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return true;
    }

    /**
     * Get a string from POST.
     */
    private static function str(string $name): string
    {
        return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
    }

    /**
     * Get an integer from POST.
     */
    private static function int(string $name): int
    {
        return isset($_POST[$name]) && is_numeric($_POST[$name]) ? (int) $_POST[$name] : 0;
    }

    /**
     * Delete current poll.
     */
    private static function deletePoll(): void
    {
        # --BEHAVIOR-- adminBeforePollsFactoryDelete -- int
        App::behavior()->callBehavior('adminBeforePollsFactoryDelete', self::$post_id);

        Polls::delPollOptions(self::$post_id);
        App::blog()->delPost(self::$post_id);

        Notices::addSuccessNotice(__('Poll has been deleted.'));
        My::redirect();
    }

    /**
     * Create or update current poll.
     */
    private static function savePoll(): void
    {
        self::$post_title    = self::str('post_title');
        self::$post_content  = self::str('post_content');
        self::$post_format   = self::str('post_format');
        self::$post_lang     = self::str('post_lang');
        self::$post_url      = self::str('post_url');
        self::$post_selected = !empty($_POST['post_selected']);
        self::$post_opened   = !empty($_POST['post_opened']);
        if (self::$can_publish && isset($_POST['post_status'])) {
            self::$post_status = self::int('post_status');
        }
        $dt            = self::str('post_dt');
        self::$post_dt = $dt !== '' ? (int) strtotime($dt) : 0;

        if (trim(self::$post_title) === '') {
            throw new Exception(__('No poll title'));
        }

        $excerpt = $excerpt_xhtml = '';
        $content = self::$post_content;
        App::blog()->setPostContent(self::$post_id, self::$post_format, self::$post_lang, $excerpt, $excerpt_xhtml, $content, self::$post_content_xhtml);

        $cur = App::blog()->openPostCursor();
        $cur->setField('post_type', My::POST_TYPE);
        $cur->setField('post_title', self::$post_title);
        $cur->setField('post_format', self::$post_format);
        $cur->setField('post_lang', self::$post_lang);
        $cur->setField('post_content', $content);
        $cur->setField('post_content_xhtml', self::$post_content_xhtml);
        $cur->setField('post_status', self::$post_status);
        $cur->setField('post_selected', (int) self::$post_selected);
        $cur->setField('post_open_tb', (int) self::$post_opened);
        $cur->setField('post_open_comment', 0);
        $cur->setField('post_dt', self::$post_dt > 0 ? date('Y-m-d H:i:00', self::$post_dt) : '');
        if (self::$post_id > 0 && isset($_POST['post_url'])) {
            $cur->setField('post_url', self::$post_url !== '' ? self::$post_url : null);
        }

        // Back to UTC in order to keep UTC datetime for creadt/upddt
        Date::setTZ('UTC');

        if (self::$post_id > 0) {
            # --BEHAVIOR-- adminBeforePollsFactoryUpdate -- Cursor, int
            App::behavior()->callBehavior('adminBeforePollsFactoryUpdate', $cur, self::$post_id);

            App::blog()->updPost(self::$post_id, $cur);

            # --BEHAVIOR-- adminAfterPollsFactoryUpdate -- Cursor, int
            App::behavior()->callBehavior('adminAfterPollsFactoryUpdate', $cur, self::$post_id);

            Notices::addSuccessNotice(__('Poll has been updated.'));
            My::redirect(['part' => 'poll', 'id' => self::$post_id]);
        } else {
            $cur->setField('user_id', App::auth()->userID());

            # --BEHAVIOR-- adminBeforePollsFactoryCreate -- Cursor
            App::behavior()->callBehavior('adminBeforePollsFactoryCreate', $cur);

            $id = App::blog()->addPost($cur);

            # --BEHAVIOR-- adminAfterPollsFactoryCreate -- Cursor, int
            App::behavior()->callBehavior('adminAfterPollsFactoryCreate', $cur, $id);

            Notices::addSuccessNotice(__('Poll has been created. You can now add queries.'));
            My::redirect(['part' => 'poll', 'id' => $id, 'tab' => 'queries'], '#queries');
        }
    }

    /**
     * Queries and selections actions.
     */
    private static function processQueries(): void
    {
        $poll_id  = self::$post_id;
        $query_id = self::int('query_id');
        $query    = $query_id > 0 ? Polls::getOptions(['option_type' => My::TYPE_QUERY, 'post_id' => $poll_id, 'option_id' => $query_id]) : null;
        $redir    = fn (string $msg) => self::redirect($msg, $query_id);

        // New query
        if (!empty($_POST['query_add'])) {
            $type = self::str('query_type');
            if (trim(self::str('query_title')) === '') {
                throw new Exception(__('No query title'));
            }
            if (!in_array($type, My::queryTypesCombo(), true)) {
                throw new Exception(__('Unknown query type'));
            }

            $cur = Polls::openCursor();
            $cur->setField('post_id', $poll_id);
            $cur->setField('option_type', My::TYPE_QUERY);
            $cur->setField('option_meta', $type);
            $cur->setField('option_lang', self::$post_lang);
            $cur->setField('option_title', trim(self::str('query_title')));
            $cur->setField('option_content', self::str('query_desc'));
            $cur->setField('option_position', Polls::nextPosition($poll_id, My::TYPE_QUERY));
            $query_id = Polls::addOption($cur);

            self::redirect(My::isChoiceType($type) ? __('Query has been created. You can now add choices.') : __('Query has been created.'), $query_id);
        }

        if (!$query instanceof MetaRecord || $query->isEmpty()) {
            return;
        }

        $type = $query->strField('option_meta');

        // Update query
        if (!empty($_POST['query_save'])) {
            if (trim(self::str('query_title')) === '') {
                throw new Exception(__('No query title'));
            }

            $cur = Polls::openCursor();
            $cur->setField('option_title', trim(self::str('query_title')));
            $cur->setField('option_content', self::str('query_desc'));

            // Query type can be changed only if there are no votes
            $new_type = self::str('query_type');
            if (!self::$has_votes && in_array($new_type, My::queryTypesCombo(), true)) {
                $cur->setField('option_meta', $new_type);
            }
            Polls::updOption($query_id, $cur);

            $redir(__('Query has been updated.'));
        }

        // Move query
        if (!empty($_POST['query_up']) || !empty($_POST['query_down'])) {
            Polls::move(Polls::getQueries($poll_id), $query_id, !empty($_POST['query_up']));

            $redir(__('Queries have been reordered.'));
        }

        // Delete query
        if (!empty($_POST['query_del'])) {
            Polls::delQuery($poll_id, $query_id);

            self::redirect(__('Query has been deleted.'));
        }

        if (!My::isChoiceType($type)) {
            return;
        }

        // Update choices
        if (!empty($_POST['selections_save'])) {
            $titles = is_array($_POST['selection_title'] ?? null) ? $_POST['selection_title'] : [];
            $dels   = is_array($_POST['selection_del'] ?? null) ? array_map(intval(...), array_filter($_POST['selection_del'], is_numeric(...))) : [];

            $rs = Polls::getSelections($poll_id, $query_id);
            while ($rs->fetch()) {
                $selection_id = $rs->intField('option_id');
                if (in_array($selection_id, $dels, true)) {
                    Polls::delSelection($poll_id, $selection_id);
                } elseif (isset($titles[$selection_id]) && is_string($titles[$selection_id]) && trim($titles[$selection_id]) !== '' && trim($titles[$selection_id]) !== $rs->strField('option_title')) {
                    $cur = Polls::openCursor();
                    $cur->setField('option_title', trim($titles[$selection_id]));
                    Polls::updOption($selection_id, $cur);
                }
            }

            // New choices, one per line
            foreach (explode("\n", self::str('selection_new')) as $title) {
                if (trim($title) === '') {
                    continue;
                }
                $cur = Polls::openCursor();
                $cur->setField('post_id', $poll_id);
                $cur->setField('option_type', My::TYPE_SELECTION);
                $cur->setField('option_meta', (string) $query_id);
                $cur->setField('option_lang', self::$post_lang);
                $cur->setField('option_title', trim($title));
                $cur->setField('option_position', Polls::nextPosition($poll_id, My::TYPE_SELECTION, (string) $query_id));
                Polls::addOption($cur);
            }

            $redir(__('Choices have been updated.'));
        }

        // Move choice
        foreach (['selection_up' => true, 'selection_down' => false] as $key => $up) {
            if (isset($_POST[$key]) && is_array($_POST[$key])) {
                $selection_id = (int) array_key_first($_POST[$key]);
                Polls::move(Polls::getSelections($poll_id, $query_id), $selection_id, $up);

                $redir(__('Choices have been reordered.'));
            }
        }
    }

    /**
     * Redirect to queries tab.
     */
    private static function redirect(string $msg, int $query_id = 0): void
    {
        Notices::addSuccessNotice($msg);
        My::redirect(['part' => 'poll', 'id' => self::$post_id, 'tab' => 'queries'], $query_id > 0 ? '#query-' . $query_id : '#queries');
    }

    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        $default_tab = isset($_GET['tab']) && $_GET['tab'] === 'queries' && self::$post_id > 0 ? 'queries' : 'edit-poll';

        // Editor
        $admin_post_behavior = '';
        $post_editor         = App::auth()->prefs()->get('interface')->get('editor');
        if (is_array($post_editor) && !empty($post_editor[self::$post_format]) && is_string($post_editor[self::$post_format])) {
            # --BEHAVIOR-- adminPostEditor -- string, string, array<int,string>, string
            $admin_post_behavior = App::behavior()->callBehavior(
                'adminPostEditor',
                $post_editor[self::$post_format],
                'pollsfactory',
                ['#post_content'],
                self::$post_format
            );
        }

        Page::openModule(
            (self::$post_id > 0 ? __('Edit poll') : __('New poll')) . ' - ' . My::name(),
            Page::jsModal() .
            $admin_post_behavior .
            Page::jsJson('post_options', [
                'entryurl_dt'    => false,
                'entryurl_title' => false,
            ]) .
            Page::jsLoad('js/_post.js') .
            Page::jsConfirmClose('entry-form') .
            Page::jsPageTabs($default_tab) .
            My::cssLoad('backend')
        );

        echo
        Page::breadcrumb([
            Html::escapeHTML(App::blog()->name()) => '',
            My::name()                            => My::manageUrl(),
            (self::$post_id > 0 ? '&ldquo;' . Html::escapeHTML(trim(Html::clean(self::$post_title))) . '&rdquo;' : __('New poll')) => '',
        ]) .
        Notices::getNotices();

        if (self::$post instanceof MetaRecord) {
            $links = [
                (new Link())
                    ->href(My::manageUrl(['part' => 'results', 'id' => self::$post_id]))
                    ->text(__('See results')),
            ];
            if (!App::status()->post()->isRestricted(self::$post_status)) {
                $links[] = (new Link())
                    ->class(['onblog_link', 'outgoing'])
                    ->href(Render::url(self::$post))
                    ->text(__('Go to this poll on the site') . ' ' . (new Img('images/outgoing-link.svg'))->alt('')->render());
            }
            echo (new Para())->separator(' - ')->items($links)->render();
        }

        if (self::$can_edit) {
            self::renderPollForm();
            if (self::$post_id > 0) {
                self::renderQueries();
            }
        }

        Page::helpBlock(My::id());
        Page::closeModule();
    }

    /**
     * Render poll form.
     */
    private static function renderPollForm(): void
    {
        $buttons = [
            (new Submit(['save'], __('Save') . ' (s)'))
                ->accesskey('s'),
        ];
        if (self::$post instanceof MetaRecord) {
            $preview_url = App::blog()->url() .
                App::url()->getURLFor(
                    'pollsfactory_preview',
                    App::auth()->userID() . '/' .
                    Http::browserUID(App::config()->masterKey() . App::auth()->userID() . App::auth()->cryptLegacy((string) App::auth()->userID())) .
                    '/' . self::$post->strField('post_url')
                );
            $buttons[] = (new Link('post-preview'))
                ->href($preview_url)
                ->class(['button', 'modal'])
                ->accesskey('p')
                ->text(__('Preview') . ' (p)');
            $buttons[] = (new Hidden(['id'], (string) self::$post_id));
        }
        if (self::$can_delete) {
            $buttons[] = (new Submit(['delete'], __('Delete')))
                ->class('delete');
        }

        $sidebar = [
            (new Para())->items([
                (new Select('post_status'))
                    ->items(App::status()->post()->combo())
                    ->default(self::$post_status)
                    ->disabled(!self::$can_publish)
                    ->label(new Label(__('Poll status'), Label::OUTSIDE_LABEL_BEFORE)),
            ]),
            (new Para())->items([
                (new Checkbox('post_opened', self::$post_opened))
                    ->value(1)
                    ->label(new Label(__('Votes are opened'), Label::IL_FT)),
            ]),
            (new Para())->items([
                (new Checkbox('post_selected', self::$post_selected))
                    ->value(1)
                    ->label(new Label(__('Selected poll'), Label::IL_FT)),
            ]),
            (new Para())->items([
                (new Datetime('post_dt'))
                    ->value(Html::escapeHTML(Date::str('%Y-%m-%dT%H:%M', self::$post_dt === 0 ? null : self::$post_dt)))
                    ->label(new Label(__('Publication date and hour'), Label::OUTSIDE_LABEL_BEFORE)),
            ]),
            (new Para())->items([
                (new Select('post_lang'))
                    ->items(self::$lang_combo)
                    ->default(self::$post_lang)
                    ->translate(false)
                    ->label(new Label(__('Poll language'), Label::OUTSIDE_LABEL_BEFORE)),
            ]),
            (new Para())->items([
                (new Select('post_format'))
                    ->items(self::$available_formats)
                    ->default(self::$post_format)
                    ->label((new Label(__('Text formatting'), Label::OUTSIDE_LABEL_BEFORE))->id('label_format')),
            ]),
        ];
        if (self::$post_id > 0) {
            $sidebar[] = (new Para())->items([
                (new Input('post_url'))
                    ->class('maximal')
                    ->value(Html::escapeHTML(self::$post_url))
                    ->size(10)
                    ->maxlength(255)
                    ->translate(false)
                    ->label(new Label(__('Edit basename'), Label::OUTSIDE_TEXT_BEFORE)),
            ]);
        }

        echo (new Div('edit-poll'))
            ->class('multi-part')
            ->title(self::$post_id > 0 ? __('Edit poll') : __('New poll'))
            ->items([
                (new Form('entry-form'))
                    ->method('post')
                    ->action(My::manageUrl(['part' => 'poll']))
                    ->fields([
                        (new Div('entry-wrapper'))
                            ->items([
                                (new Div('entry-content'))
                                    ->items([
                                        (new Div())
                                            ->class('constrained')
                                            ->items([
                                                (new Note())
                                                    ->class('form-note')
                                                    ->text(sprintf(__('Fields preceded by %s are mandatory.'), (new Span('*'))->class('required')->render())),
                                                (new Para())->items([
                                                    (new Input('post_title'))
                                                        ->value(Html::escapeHTML(self::$post_title))
                                                        ->size(20)
                                                        ->maxlength(255)
                                                        ->required(true)
                                                        ->class('maximal')
                                                        ->lang(self::$post_lang)
                                                        ->spellcheck(true)
                                                        ->label((new Label((new Span('*'))->render() . __('Title:'), Label::OUTSIDE_TEXT_BEFORE))->class(['required', 'bold'])),
                                                ]),
                                                (new Para())->class('area')->id('content-area')->items([
                                                    (new Textarea('post_content'))
                                                        ->value(Html::escapeHTML(self::$post_content))
                                                        ->cols(50)
                                                        ->rows(10)
                                                        ->lang(self::$post_lang)
                                                        ->spellcheck(true)
                                                        ->label((new Label(__('Description:'), Label::OUTSIDE_TEXT_BEFORE))->class('bold')),
                                                ]),
                                                (new Para())
                                                    ->class(['border-top', 'form-buttons'])
                                                    ->items([
                                                        App::nonce()->formNonce(),
                                                        ... $buttons,
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        (new Div('entry-sidebar'))
                            ->role('complementary')
                            ->items([
                                (new Div())->class('sb-box')->items([
                                    (new Text('h4', __('Options'))),
                                    ... $sidebar,
                                ]),
                            ]),
                    ]),
            ])
            ->render();
    }

    /**
     * Render queries part.
     */
    private static function renderQueries(): void
    {
        $poll_id    = self::$post_id;
        $queries    = Polls::getQueries($poll_id);
        $selections = [];
        $rs         = Polls::getSelections($poll_id);
        while ($rs->fetch()) {
            $selections[(int) $rs->strField('option_meta')][] = [
                'id'    => $rs->intField('option_id'),
                'title' => $rs->strField('option_title'),
            ];
        }

        $items = [];
        if (self::$has_votes) {
            $items[] = (new Note())
                ->class('warning')
                ->text(__('This poll has already votes. Changing queries or choices may distort results.'));
        }

        $nb    = $queries->count();
        $index = 0;
        while ($queries->fetch()) {
            $index++;
            $query_id = $queries->intField('option_id');
            $type     = $queries->strField('option_meta');

            $fields = [
                (new Div())->class('two-cols')->items([
                    (new Para())->class('col')->items([
                        (new Input('query_title'))
                            ->class('maximal')
                            ->size(30)
                            ->maxlength(255)
                            ->value(Html::escapeHTML($queries->strField('option_title')))
                            ->required(true)
                            ->label(new Label(__('Query:'), Label::OUTSIDE_TEXT_BEFORE)),
                    ]),
                    (new Para())->class('col')->items([
                        (new Select('query_type'))
                            ->items(My::queryTypesCombo())
                            ->default($type)
                            ->disabled(self::$has_votes)
                            ->label(new Label(__('Type:'), Label::OUTSIDE_TEXT_BEFORE)),
                    ]),
                ]),
                (new Para())->items([
                    (new Textarea('query_desc'))
                        ->class('maximal')
                        ->cols(50)
                        ->rows(2)
                        ->value(Html::escapeHTML($queries->strField('option_content')))
                        ->label(new Label(__('Description:'), Label::OUTSIDE_TEXT_BEFORE)),
                ]),
                (new Para())->class('form-buttons')->items([
                    (new Submit(['query_save'], __('Save query'))),
                    (new Submit(['query_up'], __('Move up')))->disabled($index === 1),
                    (new Submit(['query_down'], __('Move down')))->disabled($index === $nb),
                    (new Submit(['query_del'], __('Delete query')))->class('delete'),
                    ... self::hidden($query_id),
                ]),
            ];

            $choices = [];
            if (My::isChoiceType($type)) {
                $rows  = [];
                $count = count($selections[$query_id] ?? []);
                foreach ($selections[$query_id] ?? [] as $k => $selection) {
                    $rows[] = (new Tr())->items([
                        (new Td())->class('maximal')->items([
                            (new Input(['selection_title[' . $selection['id'] . ']', 'selection_title_' . $selection['id']]))
                                ->class('maximal')
                                ->size(30)
                                ->maxlength(255)
                                ->value(Html::escapeHTML($selection['title']))
                                ->title(__('Choice')),
                        ]),
                        (new Td())->class('nowrap')->items([
                            (new Submit(['selection_up[' . $selection['id'] . ']'], '↑'))->title(__('Move up'))->disabled($k === 0),
                            (new Submit(['selection_down[' . $selection['id'] . ']'], '↓'))->title(__('Move down'))->disabled($k === $count - 1),
                        ]),
                        (new Td())->class('nowrap')->items([
                            (new Checkbox(['selection_del[]', 'selection_del_' . $selection['id']]))
                                ->value($selection['id'])
                                ->label(new Label(__('Delete'), Label::IL_FT)),
                        ]),
                    ]);
                }

                $choices = [
                    (new Form('selections-form-' . $query_id))
                        ->method('post')
                        ->action(My::manageUrl(['part' => 'poll', 'id' => $poll_id]))
                        ->fields([
                            (new Text('h5', __('Choices'))),
                            $rows === [] ?
                                (new Note())->class('warning')->text(__('This query has no choice and will not be displayed.')) :
                                (new Table())->class('maximal')->items([
                                    (new Thead())->rows([(new Tr())->items([
                                        (new Th())->text(__('Choice')),
                                        (new Th())->text(__('Order')),
                                        (new Th())->text(''),
                                    ])]),
                                    (new Tbody())->rows($rows),
                                ]),
                            (new Para())->items([
                                (new Textarea(['selection_new', 'selection_new_' . $query_id]))
                                    ->class('maximal')
                                    ->cols(50)
                                    ->rows(3)
                                    ->label(new Label(__('New choices (one per line):'), Label::OUTSIDE_TEXT_BEFORE)),
                            ]),
                            (new Para())->class('form-buttons')->items([
                                (new Submit(['selections_save'], __('Save choices'))),
                                ... self::hidden($query_id),
                            ]),
                        ]),
                ];
            }

            $items[] = (new Fieldset('query-' . $query_id))
                ->class('poll-query')
                ->legend(new Legend(sprintf('%d. %s (%s)', $index, Html::escapeHTML($queries->strField('option_title')), My::queryTypeName($type))))
                ->items([
                    (new Form('query-form-' . $query_id))
                        ->method('post')
                        ->action(My::manageUrl(['part' => 'poll', 'id' => $poll_id]))
                        ->fields($fields),
                    ... $choices,
                ]);
        }

        if ($nb === 0) {
            $items[] = (new Note())->class('info')->text(__('This poll has no query yet.'));
        }

        // New query
        $items[] = (new Form('query-new-form'))
            ->method('post')
            ->action(My::manageUrl(['part' => 'poll', 'id' => $poll_id]))
            ->fields([
                (new Fieldset())
                    ->legend(new Legend(__('New query')))
                    ->items([
                        (new Div())->class('two-cols')->items([
                            (new Para())->class('col')->items([
                                (new Input(['query_title', 'new_query_title']))
                                    ->class('maximal')
                                    ->size(30)
                                    ->maxlength(255)
                                    ->required(true)
                                    ->label(new Label(__('Query:'), Label::OUTSIDE_TEXT_BEFORE)),
                            ]),
                            (new Para())->class('col')->items([
                                (new Select(['query_type', 'new_query_type']))
                                    ->items(My::queryTypesCombo())
                                    ->default('radio')
                                    ->label(new Label(__('Type:'), Label::OUTSIDE_TEXT_BEFORE)),
                            ]),
                        ]),
                        (new Para())->items([
                            (new Textarea(['query_desc', 'new_query_desc']))
                                ->class('maximal')
                                ->cols(50)
                                ->rows(2)
                                ->label(new Label(__('Description:'), Label::OUTSIDE_TEXT_BEFORE)),
                        ]),
                        (new Para())->class('form-buttons')->items([
                            (new Submit(['query_add'], __('Add query'))),
                            ... self::hidden(0),
                        ]),
                    ]),
            ]);

        echo (new Div('queries'))
            ->class('multi-part')
            ->title(__('Queries'))
            ->items($items)
            ->render();
    }

    /**
     * Queries forms hidden fields.
     *
     * @return  array<int, Component>
     */
    private static function hidden(int $query_id): array
    {
        return [
            App::nonce()->formNonce(),
            (new Hidden(['id'], (string) self::$post_id)),
            (new Hidden(['query_id'], (string) $query_id)),
            (new Hidden(['part'], 'poll')),
        ];
    }
}
