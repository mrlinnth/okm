<?php

namespace App\Libraries;

use eftec\bladeone\BladeOne;

/**
 * BladeView Library
 *
 * Integrates BladeOne templating engine with CodeIgniter 4
 *
 * @package App\Libraries
 */
class BladeView
{
    /**
     * BladeOne instance
     *
     * @var BladeOne
     */
    protected $blade;

    /**
     * Views directory path
     *
     * @var string
     */
    protected $viewsPath;

    /**
     * Cache directory path
     *
     * @var string
     */
    protected $cachePath;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set views and cache paths
        $this->viewsPath = APPPATH . 'Views';
        $this->cachePath = WRITEPATH . 'cache/blade';

        // Create cache directory if it doesn't exist
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }

        // Initialize BladeOne
        // MODE_AUTO = 0: checks if compiled file has changed
        // MODE_SLOW = 1: always recompile (development)
        // MODE_FAST = 2: never recompile (production)
        // MODE_DEBUG = 5: always compile with identifiable filenames

        $mode = ENVIRONMENT === 'production' ? BladeOne::MODE_FAST : BladeOne::MODE_AUTO;
        $this->blade = new BladeOne($this->viewsPath, $this->cachePath, $mode);
    }

    /**
     * Render a Blade view
     *
     * @param string $view View name (without .blade.php extension)
     * @param array $data Data to pass to the view
     * @return string Rendered view content
     */
    public function render(string $view, array $data = []): string
    {
        return $this->blade->run($view, $data);
    }

    /**
     * Make a Blade view (alias for render for compatibility)
     *
     * @param string $view View name (without .blade.php extension)
     * @param array $data Data to pass to the view
     * @return string
     */
    public function make(string $view, array $data = []): string
    {
        return $this->render($view, $data);
    }

    /**
     * Get the BladeOne instance
     *
     * @return BladeOne
     */
    public function getBlade(): BladeOne
    {
        return $this->blade;
    }

    /**
     * Add a custom Blade directive
     *
     * @param string $name Directive name
     * @param callable $handler Directive handler
     * @return void
     */
    public function directive(string $name, callable $handler): void
    {
        $this->blade->directive($name, $handler);
    }

    /**
     * Set the compile mode
     *
     * @param int $mode BladeOne::MODE_AUTO, MODE_SLOW, MODE_FAST, or MODE_DEBUG
     * @return void
     */
    public function setMode(int $mode): void
    {
        $this->blade->setMode($mode);
    }

    /**
     * Share a value across all views
     *
     * @param string $key Variable name
     * @param mixed $value Variable value
     * @return void
     */
    public function share(string $key, $value): void
    {
        $this->blade->share($key, $value);
    }

    /**
     * Clear the Blade cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $files = glob($this->cachePath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
