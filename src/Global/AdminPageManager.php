<?php

namespace GenWavePlugin\Global;

class AdminPageManager
{
    private $pages = [];
    private $submenus = [];

    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerPages']);
    }

    /**
     * Add a top-level admin page.
     */
    public function addPage($page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url = '', $position = null)
    {
        $this->pages[] = [
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug' => $menu_slug,
            'callback' => $callback,
            'icon_url' => $icon_url,
            'position' => $position,
        ];
    }

    /**
     * Add a submenu page under an existing menu.
     */
    public function addSubmenu($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback, $position = null)
    {
        $this->submenus[] = [
            'parent_slug' => $parent_slug,
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug' => $menu_slug,
            'callback' => $callback,
            'position' => $position,
        ];
    }

    /**
     * Register all pages and submenus.
     */
    public function registerPages()
    {
        // Register top-level admin pages.
        foreach ($this->pages as $page) {
            add_menu_page(
                $page['page_title'],
                $page['menu_title'],
                $page['capability'],
                $page['menu_slug'],
                $page['callback'],
                $page['icon_url'],
                $page['position']
            );
        }

        // Register submenus, sorted by position.
        usort($this->submenus, function ($a, $b) {
            return $a['position'] <=> $b['position'];
        });

        foreach ($this->submenus as $submenu) {
            add_submenu_page(
                $submenu['parent_slug'],
                $submenu['page_title'],
                $submenu['menu_title'],
                $submenu['capability'],
                $submenu['menu_slug'],
                $submenu['callback']
            );
        }

        // Remove default submenu links for top-level pages.
        foreach ($this->pages as $page) {
            remove_submenu_page($page['menu_slug'], $page['menu_slug']);
        }
    }
}
