<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * Blade View Service
     *
     * @param bool $getShared
     * @return \App\Libraries\BladeView
     */
    public static function blade(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('blade');
        }

        return new \App\Libraries\BladeView();
    }

    /**
     * Cockpit CMS Service
     *
     * @param bool $getShared
     * @return \App\Libraries\CockpitService
     */
    public static function cockpit(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('cockpit');
        }

        return new \App\Libraries\CockpitService();
    }

    /**
     * Aimeos E-commerce Service
     *
     * @param bool $getShared
     * @return \App\Libraries\AimeosService
     */
    public static function aimeos(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('aimeos');
        }

        return new \App\Libraries\AimeosService();
    }
}
