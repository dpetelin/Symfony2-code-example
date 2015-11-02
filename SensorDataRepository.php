<?php

namespace ISS\CoreBundle\Entity\Repository;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use ISS\CoreBundle\Entity\Container;
use Doctrine\ORM\UnexpectedResultException;

class SensorDataRepository extends EntityRepository
{
    /**
     * @param Sensor $sensor
     * @param int $period
     * @param int $startDate
     *
     * @return array
     */
    public function getLastValueForPeriod($sensor, $period, $startDate)
    {
        $qb = $this->createQueryBuilder('sp')
            ->select('sp.id')
            ->where('sp.sensor = :sensor')
            ->setParameter('sensor', $sensor);

        $this->groupByTimestampQuery($qb, $period, $startDate);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * get last sensors data for sensors with lastUpdatePeriodsAt = $startDate
     *
     * @param int  $period period for groupBy
     * @param int|null $startDate
     *
     * @return array
     */
    public function getLastValueForPeriods($period, $startDate = null)
    {
        $qb = $this->createQueryBuilder('sp')
            ->addSelect('s')
            ->leftJoin('sp.sensor', 's')
            ->groupBy('s.id');

        if ($startDate != null) {
            $qb->where('s.lastUpdatePeriodsAt = :lastUpdatePeriodsAt')
                ->andWhere('s.lastUpdateAt > :lastUpdatePeriodsAt')
                ->setParameter('lastUpdatePeriodsAt', $startDate);
        } else {
            $qb->where('s.lastUpdatePeriodsAt IS NULL');
        }

        $this->groupByTimestampQuery($qb, $period, $startDate);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array    $sensorIds
     * @param int      $startTime
     * @param int|null $endTime
     *
     * @return array
     */
    public function getSensorData($sensorIds, $startTime, $endTime = null)
    {
        $qb = $this->createQueryBuilder('sp')
            ->select('s.id, sp.measuringAt as time, sp.value as value')
            ->where('sp.measuringAt >= :startDate')
            ->setParameter('startDate', $startTime);

        $this->sensorsDataQuery($qb,$sensorIds);

        if ($endTime != null) {
            $qb->andWhere('sp.measuringAt <= :endDate')
                ->setParameter('endDate', $endTime);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param array    $sensorIds
     * @param int      $period period in minutes for groupBy
     * @param int      $startDate
     * @param int|null $endDate
     *
     * @return array
     */
    public function getSensorDataGroupByPeriod($sensorIds, $period, $startDate, $endDate = null)
    {
        $qb = $this->createQueryBuilder('sp')
            ->select('s.id')
            ->groupBy('s.id');

        $this->sensorsDataQuery($qb,$sensorIds);
        $this->groupByTimestampQuery($qb, $period, $startDate, $endDate);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param QueryBuilder $qb
     * @param int          $period
     * @param int|null     $startTime
     * @param int|null     $endTime
     */
    protected function groupByTimestampQuery(QueryBuilder $qb, $period, $startTime = null, $endTime = null)
    {
        $periodInSeconds = $period * 60;

        $qb->addSelect(
                'CEIL(sp.measuringAt/' .
                $periodInSeconds .
                ') as periodKey, CEIL(sp.measuringAt/' .
                $periodInSeconds .
                ')  * ' .
                $periodInSeconds .
                ' as time, AVG(sp.value) as value'
            )
            ->addGroupBy('periodKey');

        if ($startTime != null) {
            $startDate = ceil($startTime/$periodInSeconds) * $periodInSeconds;

            $qb->andWhere('CEIL(sp.measuringAt/' . $periodInSeconds .') * ' . $periodInSeconds . ' >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endTime != null) {
            $endDate = ceil($endTime/$periodInSeconds) * $periodInSeconds;

            $qb->andWhere('CEIL(sp.measuringAt/' . $periodInSeconds .') * ' . $periodInSeconds . ' <= :endDate')
                ->setParameter('endDate', $endDate);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array        $sensorIds
     */
    protected function sensorsDataQuery(QueryBuilder $qb, $sensorIds)
    {
        $qb->orderBy('s.id')
            ->addOrderBy('sp.measuringAt')
            ->leftJoin('sp.sensor', 's')
            ->andWhere('s.id IN (:sensorIds)')
            ->setParameter('sensorIds', $sensorIds);
    }

    /**
     * update sensor date period for measuringAt = $time
     *
     * @param mixed $sensor
     * @param int   $time
     * @param int   $value
     *
     * @return mixed
     */
    public function updateValueForPeriod($sensor, $time, $value)
    {
        $qb = $this->_em->createQueryBuilder()
            ->update($this->_entityName, 'sp')
            ->set('sp.value', $value)
            ->where('sp.measuringAt = :time')
            ->andWhere('sp.sensor = :sensor')
            ->setParameter('time', $time)
            ->setParameter('sensor', $sensor);

        return $qb->getQuery()->execute();
    }

    /**
     * @param int       $sensorId
     * @param null|int  $startTime
     *
     * @return array
     */
    public function getLastData($sensorId, $startTime = null)
    {
        $qb = $this->createQueryBuilder('sp')
            ->select('sp.measuringAt as time, sp.value')
            ->orderBy('sp.measuringAt', 'DESC')
            ->where('sp.sensor = :sensor')
            ->setParameter('sensor', $sensorId)
            ->setMaxResults(1);

        if ($startTime != null) {
            $qb->andWhere('sp.measuringAt >= :startDate')
                ->setParameter('startDate', $startTime);
        }

        try {
            return $qb->getQuery()->getSingleResult(AbstractQuery::HYDRATE_ARRAY);
        } catch (UnexpectedResultException $e) {
            return null;
        }
    }

    /**
     * @param Container     $container
     * @param int           $time
     *
     * @return array
     */
    public function findDataForTime($container, $time)
    {
        $qb = $this->createQueryBuilder('sp')
            ->innerJoin('sp.sensor', 's')
            ->where('s.container = :container')
            ->andWhere('sp.measuringAt = :time')
                ->setParameter('container', $container)
                ->setParameter('time', $time);

        return $qb->getQuery()->getResult();
    }
}
