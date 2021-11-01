<?php

namespace Brootkec\InstanceManager;

use Exception;
use PDO;

class InstanceManager
{
    public $timeMargin = 300;
    public $dbFile     = './jobs.sqlite';

    private $db    = null;
    private $time  = 0;
    private $ec2   = null;
    private $queue = array();


    public function __construct()
    {
        //var_dump($this->getAllJobs());
        //var_dump($this->getCurrentJobs());

        //var_dump($this->getInstanceType(''));

        var_dump($this->getInstanceState(''));
    }


    /* ---------------------------------
        Database
        -------------------------------- */

    /**
     *
     * @return bool
     */
    private function dbConnect(): bool
    {
        if (!is_null($this->db)) {
            return true;
        }

        try {
            $this->db = new PDO('sqlite:' . $this->dbFile);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return true;

        } catch (Exception $error) {
            die($error->getMessage());
            return false;
        }
    }


    /**
     *
     * @param array $results
     * @return array
     */
    private function parseResults(array $results): array
    {
        foreach ($results as $index => &$result) {
            foreach ($result as $key => $value) {
                switch ($key) {
                    case 'jobID':
                    case 'time':
                    case 'status':
                        $result[$key] = (int) $value;
                        break;
                }
            }
        }

        return $results;
    }


    /**
     *
     * @return array
     */
    private function getAllJobs(): array
    {
        $this->dbConnect();

        try {
            $result = $this->db->query('SELECT * FROM jobs')->fetchAll(PDO::FETCH_ASSOC);
            $result = $this->parseResults($result);

            return (is_array($result) ? $result : array());
        } catch (Exception $error) {
            die($error->getMessage());
            return array();
        }
    }


    /**
     *
     * @return array
     */
    private function getCurrentJobs(): array
    {
        $this->dbConnect();

        try {
            $timeMax = time() + $this->timeMargin;
            $timeMin = time() - $this->timeMargin;

            $query = 'SELECT * FROM jobs WHERE status = 0 AND (time >= %d AND time <= %d)';
            $query = sprintf($query, $timeMin, $timeMax);

            $result = $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
            $result = $this->parseResults($result);

            return (is_array($result) ? $result : array());

        } catch (Exception $error) {
            die($error->getMessage());
            return array();
        }
    }


    /* ---------------------------------
        AWS
        -------------------------------- */


    /**
     *
     * @return bool
     */
    private function connectEC2()
    {
        if (!is_null($this->ec2)) {
            return true;
        }

        try {
            $this->ec2 = new \Aws\Ec2\Ec2Client([
                'region'  => 'eu-west-1',
                'version' => 'latest',
                'credentials' => [
                    'key'     => 'xxx',
                    'secret'  => 'xxx'
                ]
            ]);


            $this->ec2->modifyInstanceAttribute();
            return true;

        } catch (Exception $error) {
            die($error->getMessage());
            return false;
        }
    }


    /**
     *
     * @param string $instanceID
     * @return string
     */
    private function getInstanceType(string $instanceID): string
    {
        $this->connectEC2();

        $type = $this->ec2->describeInstanceAttribute([
            'InstanceId' => $instanceID,
            'Attribute'  => 'instanceType'
        ]);

        $type = $type->get('InstanceType');
        return (empty($type) || !is_array($type) ? '' : $type['Value']);
    }


    /**
     *
     * @param string $instanceID
     * @return array
     */
    private function getInstanceData(string $instanceID): array
    {
        $this->connectEC2();

        $result = $this->ec2->describeInstances([
            'InstanceIds' => array($instanceID),
        ]);

        $result = $result->get('Reservations');

        if (!isset($result[0]['Instances'][0])) {
            return array(
                'instanceID' => 0,
                'state'      => '',
                'type'       => '',
            );
        }

        return array(
            'instanceID' => $result[0]['Instances'][0]['InstanceId'],
            'state'      => $result[0]['Instances'][0]['InstanceType'],
            'type'       => $result[0]['Instances'][0]['State']['Name'],
        );
    }


    /**
     *
     * @param string $instanceID
     * @return string
     */
    private function getInstanceState(string $instanceID): string
    {
        $this->connectEC2();

        $result = $this->ec2->describeInstanceStatus([
            'InstanceIds' => array($instanceID)
        ]);

        $result = $result->get('InstanceStatuses');
        return (!isset($result[0]['InstanceState']) ? '' : $result[0]['InstanceState']['Name']);
    }


    private function stopInstance()
    {
    }


    private function startInstance()
    {
    }


    private function changeInstanceType()
    {
    }
}

if (!file_exists('vendor/autoload.php')) {
    die('AWS-SDK is required. Install it via composer: composer require aws/aws-sdk-php');
}

require_once 'vendor/autoload.php';
new InstanceManager();
