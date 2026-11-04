<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Interface\Core\BlogWorkspaceInterface;
use Exception;

/**
 * @brief       pollsFactory install class.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Install
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        try {
            // Table is the same for plugins pollsFactory, postTask, postWidgetText
            $s = App::db()->structure();
            $s->table(My::TABLE_NAME)
                ->field('option_id', 'bigint', 0, false)
                ->field('post_id', 'bigint', 0, false)
                ->field('option_meta', 'varchar', 255, true, null)
                ->field('option_creadt', 'timestamp', 0, false, 'now()')
                ->field('option_upddt', 'timestamp', 0, false, 'now()')
                ->field('option_type', 'varchar', 32, false, "''")
                ->field('option_format', 'varchar', 32, false, "'xhtml'")
                ->field('option_lang', 'varchar', 5, true, null)
                ->field('option_title', 'varchar', 255, true, null)
                ->field('option_content', 'text', 0, true, null)
                ->field('option_content_xhtml', 'text', 0, false)
                ->field('option_selected', 'smallint', 0, false, 0)
                ->field('option_position', 'integer', 0, false, 0)

                ->index('idx_post_option_option', 'btree', 'option_id')
                ->index('idx_post_option_post', 'btree', 'post_id')
                ->index('idx_post_option_meta', 'btree', 'option_meta')
                ->index('idx_post_option_type', 'btree', 'option_type');

            try {
                App::db()->structure()->synchronize($s);
            } catch (Exception $e) {
                // SQLite can not change existing fields (varchar with NULL default),
                // this is not an issue if all fields exist.
                $fields = App::db()->con()->schema()->getColumns(App::db()->con()->prefix() . My::TABLE_NAME);
                foreach (['option_meta', 'option_selected', 'option_position'] as $field) {
                    if (!isset($fields[$field])) {
                        throw $e;
                    }
                }
            }

            // Upgrade settings from old versions
            self::growUp();

            // Settings
            $s = My::settings();
            $s->put('active', false, BlogWorkspaceInterface::NS_BOOL, 'Enable polls', false, true);
            $s->put('user_ident', My::IDENT_COOKIE, BlogWorkspaceInterface::NS_INT, 'Voters identification', false, true);
            $s->put('public_show', true, BlogWorkspaceInterface::NS_BOOL, 'Show results once voter has voted', false, true);
            $s->put('public_bars', true, BlogWorkspaceInterface::NS_BOOL, 'Use bars for results', false, true);
            $s->put('public_place', 'after', BlogWorkspaceInterface::NS_STRING, 'Place of polls in entries content', false, true);
            $s->put('public_full', true, BlogWorkspaceInterface::NS_BOOL, 'Show full poll in entries content', false, true);
            $s->put('public_tpltypes', ['post'], BlogWorkspaceInterface::NS_ARRAY, 'Pages where polls of entries are displayed', false, true);

            return true;
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return true;
    }

    /**
     * Upgrade settings from version 1.x.
     */
    private static function growUp(): void
    {
        $old = App::version()->getVersion(My::id());
        if ($old === '' || version_compare($old, '2', '>=')) {
            return;
        }

        $map = [
            'pollsFactory_active'      => ['active', BlogWorkspaceInterface::NS_BOOL],
            'pollsFactory_people_ident' => ['user_ident', BlogWorkspaceInterface::NS_INT],
            'pollsFactory_public_show' => ['public_show', BlogWorkspaceInterface::NS_BOOL],
            'pollsFactory_public_graph' => ['public_bars', BlogWorkspaceInterface::NS_BOOL],
            'pollsFactory_public_full' => ['public_full', BlogWorkspaceInterface::NS_BOOL],
        ];

        $rs = App::blogs()->getBlogs();
        while ($rs->fetch()) {
            $blog_id  = $rs->strField('blog_id');
            $settings = App::blogSettings()->createFromBlog($blog_id)->get(My::id());

            foreach ($map as $from => [$to, $type]) {
                if ($settings->settingExists($from)) {
                    $value = $settings->get($from);
                    $settings->put($to, $type === BlogWorkspaceInterface::NS_BOOL ? (bool) $value : (is_numeric($value) ? (int) $value : 0), $type);
                }
            }

            if ($settings->settingExists('pollsFactory_public_pos')) {
                $settings->put('public_place', $settings->get('pollsFactory_public_pos') ? 'after' : 'before', BlogWorkspaceInterface::NS_STRING);
            }

            if ($settings->settingExists('pollsFactory_public_tpltypes')) {
                $types = $settings->get('pollsFactory_public_tpltypes');
                $types = is_string($types) ? @unserialize($types, ['allowed_classes' => false]) : $types;
                $settings->put('public_tpltypes', is_array($types) ? array_values(array_filter($types, is_string(...))) : ['post'], BlogWorkspaceInterface::NS_ARRAY);
            }

            foreach (['active', 'user_ident', 'people_ident', 'public_show', 'public_pos', 'public_full', 'public_graph', 'graph_cache', 'graph_trigger', 'graph_options', 'public_tpltypes'] as $id) {
                $settings->drop('pollsFactory_' . $id);
            }
        }
    }
}
