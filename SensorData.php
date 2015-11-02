<?php

namespace ISS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\MappedSuperclass
 */
class SensorData
{
    const TYPE_MINUTE           = 1;
    const TYPE_QUARTER_OF_HOUR  = 15;
    const TYPE_HALF_OF_HOUR     = 30;
    const TYPE_HOUR             = 60;
    const TYPE_QUARTER_OF_DAY   = 360;
    const TYPE_HALF_OF_DAY      = 720;
    const TYPE_DAY              = 1440;

    protected $type = self::TYPE_MINUTE;

    /**
     * @var int
     *
     * @ORM\Column(name="measuring_at", type="integer", options={"unsigned"=true})
     */
    protected $measuringAt;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="value", type="decimal", precision=18, scale=14)
     */
    protected $value;

    /**
     * @var Sensor
     *
     * @ORM\ManyToOne(targetEntity="Sensor")
     * @ORM\JoinColumn(name="sensor_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $sensor;

    /**
     * @return array with format element className => periodInMinutes
     */
    public static function getUpdatedPeriods()
    {
        return array(
            'SensorDataPerHalfOfHour'       => self::TYPE_HALF_OF_HOUR,
            'SensorDataPerHour'             => self::TYPE_HOUR,
            'SensorDataPerQuarterOfDay'     => self::TYPE_QUARTER_OF_DAY,
            'SensorDataPerHalfOfDay'        => self::TYPE_HALF_OF_DAY,
            'SensorDataPerDay'              => self::TYPE_DAY,
        );
    }

    public static function getTimestampByPeriod($time, $periodInMinutes)
    {
        return ceil($time/($periodInMinutes * 60)) * $periodInMinutes * 60;
    }

    /**
     * @param int $measuringAt
     * @return SensorData
     */
    public function setMeasuringAt($measuringAt)
    {
        $this->measuringAt = $measuringAt;

        if ($this->type == self::TYPE_MINUTE) {
            $this->getSensor()->setLastUpdateAt($measuringAt);
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getMeasuringAt()
    {
        return $this->measuringAt;
    }

    /**
     * @param string $value
     * @return SensorData
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return string 
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param Sensor $sensor
     * @return SensorData
     */
    public function setSensor($sensor = null)
    {
        $this->sensor = $sensor;

        return $this;
    }

    /**
     * @return Sensor
     */
    public function getSensor()
    {
        return $this->sensor;
    }
}
