<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory\ClaudetteMcp;

/**
 * @brief       pollsFactory tool for the plugin ClaudetteMcp: list polls.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ListPolls extends PollTool
{
    public function name(): string
    {
        return 'list_polls';
    }

    public function title(): string
    {
        return 'List blog polls';
    }

    public function description(): string
    {
        return 'List the polls of the blog (plugin pollsFactory), most recent first, with their number of voters, ' .
            'if votes are opened, and the IDs of the entries showing them. Read the results with get_poll_results. ' .
            'Only published polls are listed by default.';
    }

    public function properties(): array
    {
        return [
            'status' => [
                'type'        => 'string',
                'enum'        => [...array_keys($this->statuses()), 'all'],
                'description' => 'Polls status, default: published.',
            ],
            'limit' => [
                'type'        => 'integer',
                'description' => 'Polls per page, 1 to 50, default: 20.',
            ],
            'page' => [
                'type'        => 'integer',
                'description' => 'Page number, default: 1.',
            ],
        ];
    }

    public function call(array $args): array
    {
        $status = $this->str($args, 'status', 'published');
        $limit  = $this->int($args, 'limit', 20, 1, 50);
        $page   = $this->int($args, 'page', 1, 1);

        $total = $this->polls($status, [], true)->intField(0);
        $rs    = $this->polls($status, ['no_content' => true, 'limit' => [($page - 1) * $limit, $limit]]);
        $polls = [];
        while ($rs->fetch()) {
            $polls[] = $this->describePoll($rs);
        }

        return [
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'polls' => $polls,
        ];
    }
}
