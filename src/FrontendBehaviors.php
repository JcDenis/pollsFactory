<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\File\Path;

/**
 * @brief       pollsFactory frontend behaviors.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class FrontendBehaviors
{
    /**
     * Add plugin CSS.
     */
    public static function publicHeadContent(): void
    {
        echo My::cssLoad('frontend');
    }

    /**
     * Add plugin templates path.
     */
    public static function publicBeforeDocumentV2(): void
    {
        $theme  = App::blog()->settings()->get('system')->getStr('theme', false);
        $tplset = is_string($tplset = App::themes()->moduleInfo($theme, 'tplset')) ? $tplset : '';
        $root   = Path::real(My::path()) . DIRECTORY_SEPARATOR . 'default-templates' . DIRECTORY_SEPARATOR;

        App::frontend()->template()->setPath(
            App::frontend()->template()->getPath(),
            $root . ($tplset !== '' && is_dir($root . $tplset) ? $tplset : App::config()->defaultTplset())
        );
    }

    /**
     * Polls before entry content.
     */
    public static function publicEntryBeforeContent(): void
    {
        if (My::publicPlace() === 'before') {
            echo self::pollsOfEntry();
        }
    }

    /**
     * Polls after entry content.
     */
    public static function publicEntryAfterContent(): void
    {
        if (My::publicPlace() === 'after') {
            echo self::pollsOfEntry();
        }
    }

    /**
     * Polls of current entry.
     */
    public static function pollsOfEntry(): string
    {
        $post = App::frontend()->context()->__get('posts');
        if (!$post instanceof MetaRecord
            || $post->isEmpty()
            || $post->strField('post_type') === My::POST_TYPE
            || !App::url()->isType(My::tplTypes())
        ) {
            return '';
        }

        $polls = Polls::getPollsOfPost($post->intField('post_id'));
        if ($polls === []) {
            return '';
        }

        if (My::settings()->getBool('public_full', false)) {
            $res = '';
            foreach ($polls as $poll_id) {
                $res .= Render::poll($poll_id, true, true);
            }

            return $res;
        }

        $links = Render::links($polls);

        return $links === '' ? '' : '<div class="pollsfactory polls-of-entry"><h3>' . __('Polls') . '</h3>' . $links . '</div>';
    }
}
