<?php

namespace drvy\InstanceManager;

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


    /**
     *
     * Example:
     *  var_dump($this->getAllJobs());
     *  var_dump($this->getCurrentJobs());
     *  var_dump($this->getInstanceType(''));
     *  var_dump($this->getInstanceState(''));
     *
     * @return void
     */
    public function __construct()
    {
    }


    /* ---------------------------------
        Database
        -------------------------------- */

    /**
     * Connect to sqlite database
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
     * Parse the database results into an array for easy access.
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
     * Return all jobs from the database
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
     * Get jobs expected to be executed by now.
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
     * Connect to AWS EC2.
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

            return true;
        } catch (Exception $error) {
            die($error->getMessage());
            return false;
        }
    }


    /**
     * Get the type of instance
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
     * Get the instanceID, state and type
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
     * Get the instance state
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


    /**
     * Stop a currently running instance
     *
     * @return void
     */
    private function stopInstance()
    {
    }


    /**
     * start a currently stoppted instance
     *
     * @return void
     */
    private function startInstance()
    {
    }


    /**
     * Change the type of instance. Requires stoping and starting the instance
     * in the process
     *
     * @return void
     */
    private function changeInstanceType()
    {
    }
}

if (!file_exists('vendor/autoload.php')) {
    die('AWS-SDK is required. Install it via composer: composer require aws/aws-sdk-php');
}

require_once 'vendor/autoload.php';
new InstanceManager();
