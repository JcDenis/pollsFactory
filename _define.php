<?php
/**
 * @file
 * @brief       The plugin pollsFactory definition
 * @ingroup     pollsFactory
 *
 * @defgroup    pollsFactory Plugin pollsFactory.
 *
 * Create polls and add them to entries.
 *
 * @author      Jean-Christian Denis (author)
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

if (!isset($this) || !is_object($this) || !method_exists($this, 'registerModule') || !isset($this->id) || !is_string($this->id)) {
    return;
}

$this->registerModule(
    'Polls manager',
    'Create polls and add them to entries',
    'Jean-Christian Denis',
    '2026.11.05',
    [
        'requires'    => [['core', '2.39']],
        'permissions' => 'My',
        'type'        => 'plugin',
        'support'     => 'https://github.com/JcDenis/' . $this->id . '/issues',
        'details'     => 'https://github.com/JcDenis/' . $this->id . '/',
        'repository'  => 'https://raw.githubusercontent.com/JcDenis/' . $this->id . '/master/dcstore.xml',
        'date'        => '2026-11-04T00:00:00+00:00',
    ]
);
