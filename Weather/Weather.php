<?php
/**
 * User: Abdallah ARFFAK (arffak@gmail.com)
 * Date: 28/10/12
 * Time: 16:13
 */
namespace ELAR\WeatherBundle\Weather;
use Symfony\Component\Config\FileLocator;
use ELAR\WeatherBundle\Weather\Yr;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Weather
{
    private $Weather;

    function __construct(ContainerInterface $container)
    {
        $this->Weather = new Yr($container);
    }

    function load(array $lng_lat_alt = array())
    {
        $this->Weather->getForecastByLatLngAlt($lng_lat_alt);
        $this->Weather->getSunriseByLatLng($lng_lat_alt, 10);
        /** 10 jours car pour l'api ($this->Weather->getForecastByLatLngAlt($lng_lat_alt);) nous renvoi just 10 jours de prévisions */
    }

    function getForecast($period, $how_many_days)
    {
        return $this->Weather->getForecastByPeriod($period, $how_many_days);
    }

    function getSunrise($how_many_days)
    {
        return $this->Weather->getSunrise($how_many_days);

    }

    function getForecastRunEnded()
    {
        return $this->Weather->getForecastRunEnded();
    }

    public static function getForecastDescription($symbolId)
    {
        return Yr::getForecastDescription($symbolId);
    }
}
