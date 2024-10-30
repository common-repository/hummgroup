<?php
class Humm_Logger
{
    private static $logger;
    public static function getLogger()
    {
        if (! isset(self::$logger)) {
            self::$logger = wc_get_logger();
        }
        return self::$logger;
    }
    public static function log($content)
    {
        self::getLogger()->info(json_encode($content), array( 'source' => 'humm'));
    }
}
