<?php
/**
 * User: Abdallah ARFFAK (arffak@gmail.com)
 * Date: 28/10/12
 * Time: 20:54
 */
namespace ELAR\WeatherBundle\Weather;
use ELAR\WeatherBundle\Weather\XMLLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Yr
{
    private $apiUriBase = "http://api.yr.no/weatherapi/";
    private $apiLocationForecast = "locationforecast/1.8/";
    private $apiSunrise = "sunrise/1.0/";
    private $dataForecast;
    private $dataSunrise;
    private $period = array(
        'PERIOD_BEFORE_MORNING' => array("%date%T00:00:00Z", "%date%T06:00:00Z"),
        'PERIOD_MORNING' => array("%date%T06:00:00Z", "%date%T12:00:00Z"),
        'PERIOD_AFTERNOON' => array("%date%T12:00:00Z", "%date%T18:00:00Z"),
        'PERIOD_EVENING' => array("%date%T18:00:00Z", "%date%T00:00:00Z")
    );
    private $periods = array(
        'PERIOD_BEFORE_MORNING' ,
        'PERIOD_MORNING' ,
        'PERIOD_AFTERNOON' ,
        'PERIOD_EVENING'
    );
    private $container;
    private $translator;

    function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->translator = $this->container->get('translator');
    }

    public function getForecastByLatLngAlt(array $lng_lat_alt = array())
    {
        if (!empty($lng_lat_alt) && !empty($lng_lat_alt['lat']) && !empty($lng_lat_alt['lng'])) {
            $dataForecast = new XMLLoader($this->apiUriBase . $this->apiLocationForecast . "?lat=" . $lng_lat_alt['lat'] . ";lon=" . $lng_lat_alt['lng'] . ((isset($lng_lat_alt['alt']) && !empty($lng_lat_alt['alt'])) ? "&msl=" . $lng_lat_alt['alt'] : ''));
            $this->dataForecast = $dataForecast->load();
        } else
            throw new \Exception('Data errors1');


    }

    public function getSunriseByLatLng(array $lng_lat_alt = array(), $how_many_days)
    {
        $dateToday = new \DateTime(date("Y-m-d H:i:s"));
        $dateTo = new \DateTime(date("Y-m-d H:i:s"));
        $dateTo->add((new \DateInterval('P' . $how_many_days . 'D')));
        $dataSunrise = new XMLLoader($this->apiUriBase . $this->apiSunrise . "?lat=" . $lng_lat_alt['lat'] . ";lon=" . $lng_lat_alt['lng'] . "&from=" . $dateToday->format("Y-m-d") . "&to=" . $dateTo->format("Y-m-d"));
        $this->dataSunrise = $dataSunrise->load();
    }


    /**
     * @param string $address
     */
    private function getLatLngByAddress(string $address)
    {
        //http://maps.googleapis.com/maps/api/geocode/json?address=1600+Amphitheatre+Parkway,+Mountain+View,+CA&sensor=true_or_false

    }

    /**
     * $period => before_morning,morning,afternoon,evening
     * @param $period
     */
    public function getForecastByPeriod($periods = 'all', $how_many_days = 1)
    {
        $periods = (is_array($periods)) ? $periods : ($periods == 'all' ? $this->periods : array($periods));
        $data = array();
        for ($cpt = 0; $cpt < $how_many_days; $cpt++) {
            $dataTmp = array();
			$dtmp = (new \DateTime(date("Y-m-d")));
            $currentDate = $dtmp->add(new \DateInterval('P' . $cpt . 'D'))->format("Y-m-d");
            foreach ($periods as $period) {
                $dataTmp[$period] =
                    array('humidity' => $this->getDetailHumidity($period, $cpt),
                        'wind-speed' => $this->getDetailWindSpeed($period, $cpt),
                        'fog' => $this->getDetailFog($period, $cpt),
                        'cloudiness' => $this->getDetailCloudiness($period, $cpt),
                        'temperature' => $this->getDetailTemperature($period, $cpt),
                        'description' => $this->getForecastDescription($this->getTemperatureDetailSymbol($period, $cpt)),
                        'symbol' => $this->getTemperatureDetailSymbol($period, $cpt));
            }
            $data[$currentDate] = $dataTmp;
            $data[$currentDate]['temperature-moy-min'] = $this->getTemperatureMoyMin($dataTmp);
            $data[$currentDate]['temperature-moy-max'] = $this->getTemperatureMoyMax($dataTmp);
            $data[$currentDate]['symbol'] = $this->getTemperatureMoySymbol($dataTmp);
        }

        return $data;
    }

    public function getSunrise($how_many_days)
    {
        $how_many_days = ($how_many_days == 'today') ? 0 : $how_many_days;
        $data = array();
        for ($cpt = 0; $cpt < $how_many_days; $cpt++) {
			$dtmp = (new \DateTime(date("Y-m-d")));
            $currentDate = $dtmp->add(new \DateInterval('P' . $cpt . 'D'))->format("Y-m-d");
            $dataTmp = array(
                'sunrise' => $this->getDetailSunrise($cpt),
                'sunset' => $this->getDetailSunset($cpt)
            );
            $data[$currentDate] = $dataTmp;
        }
        return $data;
    }

    private function getTemperatureMoyMin($data)
    {
        $temperatureMoyMin = 1000;
        foreach ($data as $item) {
            if (!is_null($item['temperature']) && (float)$item['temperature'] < $temperatureMoyMin)
                $temperatureMoyMin = (float)$item['temperature'];
        }
        return ($temperatureMoyMin == 1000) ? null: $temperatureMoyMin;
    }

    private function getTemperatureMoyMax($data)
    {
        $temperatureMoyMin = -1000;
        foreach ($data as $item) {
            if (!is_null($item['temperature']) && (float)$item['temperature'] > $temperatureMoyMin)
                $temperatureMoyMin = (float)$item['temperature'];
        }
        return ($temperatureMoyMin == -1000) ? null: $temperatureMoyMin;
    }

    private function getTemperatureDetailSymbol($period, $day = 0)
    {
        $node = $this->getDetailTwo('location/symbol', $period, $day);
        if (($node = isset($node[0]) ? $node[0] : false) && isset($node->attributes()->number))
            return (string)$node->attributes()->number;
        return null;
    }

    private function getTemperatureMoySymbol($data)
    {
        $temperatureMoySymbol = null;
        $temperatureMoyMin = -1000;
        foreach ($data as $item) {
            if (!is_null($item['temperature']) && (float)$item['temperature'] > $temperatureMoyMin) {
                $temperatureMoyMin = (float)$item['temperature'];
                $temperatureMoySymbol = (integer)$item['symbol'];
            }
        }
        return ($temperatureMoyMin == -1000) ? null: $temperatureMoySymbol;
    }

    private function getDetailTwo($xpath, $period, $day = 0)
    {
        if (false !== $nodes = $this->dataForecast->xpath('/weatherdata/product/time')) {
            foreach ($nodes as $node) {
                $dateFrom = new \DateTime((string)$node->attributes()->from);
                $dateTo = new \DateTime((string)$node->attributes()->to);
                $dateToday = new \DateTime(date("Y-m-d H:i:s"));
                $dateToday->add(new \DateInterval('P' . $day . 'D'));
                $dateItemFrom = new \DateTime(str_replace('%date%', $dateToday->format("Y-m-d"), $this->period[$period][0]));
                if ($period == 'PERIOD_EVENING')
                    $dateToday->add(new \DateInterval('P1D'));
                $dateItemTo = new \DateTime(str_replace('%date%', $dateToday->format("Y-m-d"), $this->period[$period][1]));
                if ($dateFrom->getTimestamp() != $dateTo->getTimestamp()
                    && $dateItemTo->getTimestamp() == $dateTo->getTimestamp()
                    && $dateItemFrom->getTimestamp() == $dateFrom->getTimestamp()
                ) {
                    return (false !== $nodeItem = $node->xpath($xpath)) ? $nodeItem : false;
                }
            }

        }
        return false;
    }

    private function getDetailTypeOne($xpath, $period, $day = 0)
    {
        if (false !== $nodes = $this->dataForecast->xpath('/weatherdata/product/time')) {
            foreach ($nodes as $node) {
                $dateFrom = new \DateTime((string)$node->attributes()->from);
                $dateTo = new \DateTime((string)$node->attributes()->to);
                $dateToday = new \DateTime(date("Y-m-d H:i:s"));
                $dateToday->add(new \DateInterval('P' . $day . 'D'));
                if ($dateFrom->getTimestamp() == $dateTo->getTimestamp()) {
                    $dateItem = str_replace('%date%', $dateToday->format("Y-m-d"), $this->period[$period][0]);
                    $dateItem = new \DateTime($dateItem);
                    if ($dateItem->getTimestamp() == $dateFrom->getTimestamp()) {
                        return (false !== $nodeItem = $node->xpath($xpath)) ? $nodeItem : false;

                    }

                }
            }
        }
        return false;
    }

    private function getDetailTemperature($period, $day = 0)
    {
        $node = $this->getDetailTypeOne('location/temperature', $period, $day);
        if (($node = isset($node[0]) ? $node[0] : false) && isset($node->attributes()->value) && isset($node->attributes()->unit))
            return $node->attributes()->value;
        return null;

    }

    public function getForecastRunEnded()
    {
        if (false !== $nodes = $this->dataForecast->xpath('/weatherdata/meta/model')) {
            {
                foreach ($nodes as $node) {
                    if (false != $node->attributes()->runended) {
                        $dtmp = (new \DateTime($node->attributes()->runended));
                        return $dtmp->format("Y-m-d H:i:s");
                    }
                }

            }
        }
        return false;

    }

    private function getDetailHumidity($period, $day = 0)
    {
        $node = $this->getDetailTypeOne('location/humidity', $period, $day);
        if (($node = isset($node[0]) ? $node[0] : false) && isset($node->attributes()->value) && isset($node->attributes()->unit))
            return $node->attributes()->value;
        return null;
    }

    private function getDetailWindSpeed($period, $day = 0)
    {
        $node = $this->getDetailTypeOne('location/windSpeed', $period, $day);
        if (($node = isset($node[0]) ? $node[0] : false) && isset($node->attributes()->mps))
            return $node->attributes()->mps;
        return null;
    }

    private function getDetailFog($period, $day = 0)
    {
        $node = $this->getDetailTypeOne('location/fog', $period, $day);
        if (($node = isset($node[0]) ? $node[0] : false) && isset($node->attributes()->percent))
            return $node->attributes()->percent;
        return null;
    }

    private function getDetailCloudiness($period, $day = 0)
    {
        $node = $this->getDetailTypeOne('location/cloudiness', $period, $day);
        if (($node = isset($node[0]) ? $node[0] : false) && isset($node->attributes()->percent))
            return $node->attributes()->percent;
        return null;
    }

    private function getDetailSun($day = 0)
    {
        if (false !== $nodes = $this->dataSunrise->xpath('/astrodata/time')) {
            {
                foreach ($nodes as $node) {
                    $date = new \DateTime((string)$node->attributes()->date);
                    $dateToday = new \DateTime(date("Y-m-d"));
                    $dateToday->add(new \DateInterval('P' . $day . 'D'));

                    if ($dateToday->getTimestamp() == $date->getTimestamp())
                        return $node;
                }

            }
        }
        return false;
    }

    private function getDetailSunrise($day = 0)
    {
        $node = $this->getDetailSun($day);
        if (false !== $node = $node->xpath('location/sun')) {
            if (($node = isset($node[0]) ? $node[0] : false) && isset($node->attributes()->rise)){
				$dtmp = (new \DateTime($node->attributes()->rise));
                return $dtmp->format('Y-m-d H:i:s');
			}
        }
        return false;
    }

    private function getDetailSunset($day = 0)
    {
        $node = $this->getDetailSun($day);
        if (false !== $node = $node->xpath('location/sun')) {
            if (($node = isset($node[0]) ? $node[0] : false) && isset($node->attributes()->set)){
				$dtmp = (new \DateTime($node->attributes()->set));
                return $dtmp->format('Y-m-d H:i:s');
			}
        }
        return false;
    }

    public function getForecastByAddress(string $address, int $how_many_days)
    {
        $lng_lat_alt = $this->getLatLngByAddress($address);
        $this->getForecastByLatLngAlt($lng_lat_alt, $how_many_days);
    }

    public static function getForecastDescription($symbolId)
    {
        $description = null;
        switch ($symbolId) {
            case 1:
                $description = "Soleil";
                break;
            case 2:
                $description = "Partiellement nuageux";
                break;
            case 3:
                $description = "Partiellement nuageux";
                break;
            case 4:
                $description = "Nuageux";
                break;
            case 5:
                $description = "Averses";
                break;
            case 6:
                $description = "Orages";
                break;
            case 7:
                $description = "Grêle";
                break;
            case 8:
                $description = "Neige";
                break;
            case 9:
                $description = "Faible pluie";
                break;
            case 10:
                $description = "Pluie";
                break;
            case 11:
                $description = "Orages";
                break;
            case 12:
                $description = "Grêle";
                break;
            case 13:
                $description = "Neige";
                break;
            case 14:
                $description = "Tempête de neige";
                break;
            case 15:
                $description = "Brouillard";
                break;
            case 16:
                $description = "Soleil";
                break;
            case 17:
                $description = "Partiellement nuageux";
                break;
            case 18:
                $description = "Averses";
                break;
            case 19:
                $description = "Neige";
                break;
            case 20:
                $description = "Averses de grêle";
                break;
            case 21:
                $description = "Averses de neige";
                break;
            case 22:
                $description = "Faible pluie";
                break;
            case 23:
                $description = "Tempête Grêle";
                break;
        }
        return $description;
    }
}
