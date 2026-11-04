<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use ArrayObject;

/**
 * @brief       pollsFactory template tags.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class FrontendTemplate
{
    /**
     * tpl:PollForm : Form (or results) of current poll
     *
     * attributes:
     *
     *      - title       (0|1)   Show poll title (default 0)
     *      - description (0|1)   Show poll description (default 0)
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function PollForm(ArrayObject $attr): string
    {
        return '<?php echo ' . Render::class . '::poll(' .
            'App::frontend()->context()->posts->intField(\'post_id\'), ' .
            (empty($attr['title']) ? 'false' : 'true') . ', ' .
            (empty($attr['description']) ? 'false' : 'true') .
            '); ?>';
    }

    /**
     * tpl:PollsOfEntry : Polls of current entry
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function PollsOfEntry(ArrayObject $attr): string
    {
        return '<?php echo ' . FrontendBehaviors::class . '::pollsOfEntry(); ?>';
    }
}
