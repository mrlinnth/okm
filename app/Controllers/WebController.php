<?php

namespace App\Controllers;

use App\Libraries\AimeosService;
use App\Libraries\BladeView;
use App\Libraries\CockpitService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Config\Services;

/**
 * WebController
 *
 * Base controller for all HTML pages (Home, About, Blog, etc.)
 * Handles initialization of CMS and View engine
 *
 * All web pages should extend this controller instead of BaseController
 *
 * @package App\Controllers
 * @method string render(string $view, array $data = []) Render a Blade view
 */
abstract class WebController extends BaseController
{
    /**
     * Blade View instance
     *
     * @var BladeView
     */
    protected $blade;

    /**
     * Cockpit Service instance
     *
     * @var CockpitService
     */
    protected $cockpit;

    /**
     * Aimeos Service instance
     *
     * @var AimeosService
     */
    protected $aimeos;

    /**
     * Initialize controller
     *
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // Initialize Blade view engine
        $this->blade = Services::blade();

        // Initialize Cockpit CMS service
        $this->cockpit = Services::cockpit();

        // Initialize Aimeos e-commerce service
        $this->aimeos = Services::aimeos();
    }

    /**
     * Render a Blade view
     *
     * @param string $view View name (without .blade.php extension)
     * @param array $data Data to pass to the view
     * @return string
     */
    protected function render(string $view, array $data = []): string
    {
        return $this->blade->render($view, $data);
    }
}
