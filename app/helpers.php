<?php

if (!function_exists('date_time_format')) {
    /**
     * @param string $dateTime
     * @param string $format
     *
     * @return string
     * @throws \Exception
     */
    function date_time_format(string $dateTime, $format = 'd-M-Y'): string
    {
        $dateTimeObject = new \DateTime($dateTime);
        return $dateTimeObject->format($format);
    }
}
