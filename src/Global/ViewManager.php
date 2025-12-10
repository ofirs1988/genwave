<?php

namespace GenWavePlugin\Global;

class ViewManager {
    protected $views_dir;

    public function __construct($views_dir = '',$proPlugin = false) {
        // Set the directory where views are located
        if($proPlugin){
            $this->views_dir = GEN_WAVE_PRO_PATH . 'views/';
        }else{
            $this->views_dir = GEN_WAVE_PATH . 'views/';
        }


    }

    /**
     * Render a view file and pass data to it.
     *
     * @param string $view The name of the view file to render.
     * @param array $data Associative array of data to pass to the view.
     */
    public function render($view, $data = []) {
        $view_file = $this->views_dir . $view . '.php';
        if (file_exists($view_file)) {
            extract($data);
            include $view_file;
        } else {
            wp_die("View file not found: " . esc_html($view_file));
        }
    }
}
