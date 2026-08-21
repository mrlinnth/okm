<?php

namespace App\Controllers;

use App\Libraries\CockpitService;

class About extends WebController
{
    public function index(): string
    {
        $cockpit = new CockpitService();

        // Fetch settings singleton from Cockpit
        $settings = $cockpit->getSingletonCached('settings');

        $data = [
            'title' => 'About Us',
            'settings' => $settings,
        ];

        return $this->render('about', $data);
    }
}
