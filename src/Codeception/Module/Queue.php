<?php
namespace Codeception\Module;

use Codeception\Module as CodeceptionModule;
use Codeception\TestInterface;
use Codeception\Exception\ModuleConfigException;
use Codeception\Lib\Driver\AmazonSQS;
use Codeception\Lib\Driver\Beanstalk;
use Codeception\Lib\Driver\Iron;

/**
 *
 * Works with Queue servers.
 *
 * Testing with a selection of remote/local queueing services, including Amazon's SQS service
 * Iron.io service and beanstalkd service.
 *
 * Supported and tested queue types are:
 *
 * * [Iron.io](https://iron.io/)
 * * [Beanstalkd](https://kr.github.io/beanstalkd/)
 * * [Amazon SQS](https://aws.amazon.com/sqs/)
 *
 * The following dependencies are needed for the listed queue servers:
 *
 * * Beanstalkd: pda/pheanstalk ~3.0
 * * Amazon SQS: aws/aws-sdk-php
 * * IronMQ: iron-io/iron_mq
 *
 * ## Status
 *
 * * Stability:
 *     - Iron.io:    **stable**
 *     - Beanstalkd: **stable**
 *     - Amazon SQS: **stable**
 *
 * ## Config
 *
 * The configuration settings depending on which queueing service is being used, all the options are listed
 * here. Refer to the configuration examples below to identify the configuration options required for your chosen
 * service.
 *
 * * type - type of queueing server (defaults to beanstalkd).
 * * host - hostname/ip address of the queue server or the host for the iron.io when using iron.io service.
 * * port: 11300 - port number for the queue server.
 * * timeout: 90 - timeout settings for connecting the queue server.
 * * token - Iron.io access token.
 * * project - Iron.io project ID.
 * * key - AWS access key ID.
 * * version - AWS version (e.g. latest)
 * * endpoint - The full URI of the webservice. This is only required when connecting to a custom endpoint (e.g., a local version of SQS).
 * * secret - AWS secret access key.
 *      Warning:
 *          Hard-coding your credentials can be dangerous, because it is easy to accidentally commit your credentials
 *          into an SCM repository, potentially exposing your credentials to more people than intended.
 *          It can also make it difficult to rotate credentials in the future.
 * * profile - AWS credential profile
 *           - it should be located in ~/.aws/credentials file
 *           - eg:  [default]
 *                  aws_access_key_id = YOUR_AWS_ACCESS_KEY_ID
 *                  aws_secret_access_key = YOUR_AWS_SECRET_ACCESS_KEY
 *                  [project1]
 *                  aws_access_key_id = YOUR_AWS_ACCESS_KEY_ID
 *                  aws_secret_access_key = YOUR_AWS_SECRET_ACCESS_KEY
 *          - Note: Using IAM roles is the preferred technique for providing credentials
 *                  to applications running on Amazon EC2
 *                  https://docs.aws.amazon.com/aws-sdk-php/v3/guide/guide/credentials.html?highlight=credentials
 *
 * * region - A region parameter is also required for AWS, refer to the AWS documentation for possible values list.
 *
 * ### Example
 * #### Example (beanstalkd)
 *
 *     modules:
 *        enabled: [Queue]
 *        config:
 *           Queue:
 *              type: 'beanstalkd'
 *              host: '127.0.0.1'
 *              port: 11300
 *              timeout: 120
 *
 * #### Example (Iron.io)
 *
 *     modules:
 *        enabled: [Queue]
 *        config:
 *           Queue:
 *              'type': 'iron',
 *              'host': 'mq-aws-us-east-1.iron.io',
 *              'token': 'your-token',
 *              'project': 'your-project-id'
 *
 * #### Example (AWS SQS)
 *
 *     modules:
 *        enabled: [Queue]
 *        config:
 *           Queue:
 *              'type': 'aws',
 *              'key': 'your-public-key',
 *              'secret': 'your-secret-key',
 *              'region': 'us-west-2'
 *
 * #### Example AWS SQS using profile credentials
 *
 *     modules:
 *        enabled: [Queue]
 *        config:
 *           Queue:
 *              'type': 'aws',
 *              'profile': 'project1', //see documentation
 *              'region': 'us-west-2'
 *
 * #### Example AWS SQS running on Amazon EC2 instance
 *
 *     modules:
 *        enabled: [Queue]
 *        config:
 *           Queue:
 *              'type': 'aws',
 *              'region': 'us-west-2'
 *
 */
class Queue extends CodeceptionModule
{
    /**
     * @var \Codeception\Lib\Interfaces\Queue
     */
    public $queueDriver;

    /**
     * Setup connection and open/setup the connection with config settings
     *
     * @param \Codeception\TestInterface $test
     */
    public function _before(TestInterface $test)
    {
        $this->queueDriver->openConnection($this->config);
    }

    /**
     * Provide and override for the config settings and allow custom settings depending on the service being used.
     */
    protected function validateConfig()
    {
        $this->queueDriver = $this->createQueueDriver();
        $this->requiredFields = $this->queueDriver->getRequiredConfig();
        $this->config = array_merge($this->queueDriver->getDefaultConfig(), $this->config);
        parent::validateConfig();
    }

    /**
     * @return \Codeception\Lib\Interfaces\Queue
     * @throws ModuleConfigException
     */
    protected function createQueueDriver()
    {
        switch ($this->config['type']) {
            case 'aws':
            case 'sqs':
            case 'aws_sqs':
                return new AmazonSQS();
            case 'iron':
            case 'iron_mq':
                return new Iron();
            case 'beanstalk':
            case 'beanstalkd':
            case 'beanstalkq':
                return new Beanstalk();
            default:
                throw new ModuleConfigException(
                    __CLASS__,
                    "Unknown queue type {$this->config}; Supported queue types are: aws, iron, beanstalk"
                );
        }
    }

    // ----------- SEARCH METHODS BELOW HERE ------------------------//

    /**
     * Check if a queue/tube exists on the queueing server.
     *
     * ```php
     * <?php
     * $I->seeQueueExists('default');
     * ?>
     * ```
     *
     * @param string $queue Queue Name
     */
    public function seeQueueExists($queue)
    {
        $this->assertContains($queue, $this->queueDriver->getQueues());
    }

    /**
     * Check if a queue/tube does NOT exist on the queueing server.
     *
     * ```php
     * <?php
     * $I->dontSeeQueueExists('default');
     * ?>
     * ```
     *
     * @param string $queue Queue Name
     */
    public function dontSeeQueueExists($queue)
    {
        $this->assertNotContains($queue, $this->queueDriver->getQueues());
    }

    /**
     * Check if a queue/tube is empty of all messages
     *
     * ```php
     * <?php
     * $I->seeEmptyQueue('default');
     * ?>
     * ```
     *
     * @param string $queue Queue Name
     */
    public function seeEmptyQueue($queue)
    {
        $this->assertEquals(0, $this->queueDriver->getMessagesCurrentCountOnQueue($queue));
    }

    /**
     * Check if a queue/tube is NOT empty of all messages
     *
     * ```php
     * <?php
     * $I->dontSeeEmptyQueue('default');
     * ?>
     * ```
     *
     * @param string $queue Queue Name
     */
    public function dontSeeEmptyQueue($queue)
    {
        $this->assertNotEquals(0, $this->queueDriver->getMessagesCurrentCountOnQueue($queue));
    }

    /**
     * Check if a queue/tube has a given current number of messages
     *
     * ```php
     * <?php
     * $I->seeQueueHasCurrentCount('default', 10);
     * ?>
     * ```
     *
     * @param string $queue Queue Name
     * @param int $expected Number of messages expected
     */
    public function seeQueueHasCurrentCount($queue, $expected)
    {
        $this->assertEquals($expected, $this->queueDriver->getMessagesCurrentCountOnQueue($queue));
    }

    /**
     * Check if a queue/tube does NOT have a given current number of messages
     *
     * ```php
     * <?php
     * $I->dontSeeQueueHasCurrentCount('default', 10);
     * ?>
     * ```
     *
     * @param string $queue Queue Name
     * @param int $expected Number of messages expected
     */
    public function dontSeeQueueHasCurrentCount($queue, $expected)
    {
        $this->assertNotEquals($expected, $this->queueDriver->getMessagesCurrentCountOnQueue($queue));
    }

    /**
     * Check if a queue/tube has a given total number of messages
     *
     * ```php
     * <?php
     * $I->seeQueueHasTotalCount('default', 10);
     * ?>
     * ```
     *
     * @param string $queue Queue Name
     * @param int $expected Number of messages expected
     */
    public function seeQueueHasTotalCount($queue, $expected)
    {
        $this->assertEquals($expected, $this->queueDriver->getMessagesTotalCountOnQueue($queue));
    }

    /**
     * Check if a queue/tube does NOT have a given total number of messages
     *
     * ```php
     * <?php
     * $I->dontSeeQueueHasTotalCount('default', 10);
     * ?>
     * ```
     *
     * @param string $queue Queue Name
     * @param int $expected Number of messages expected
     */
    public function dontSeeQueueHasTotalCount($queue, $expected)
    {
        $this->assertNotEquals($expected, $this->queueDriver->getMessagesTotalCountOnQueue($queue));
    }

    // ----------- UTILITY METHODS BELOW HERE -------------------------//

    /**
     * Add a message to a queue/tube
     *
     * ```php
     * <?php
     * $I->addMessageToQueue('this is a messages', 'default');
     * ?>
     * ```
     *
     * @param string $message Message Body
     * @param string $queue Queue Name
     */
    public function addMessageToQueue($message, $queue)
    {
        $this->queueDriver->addMessageToQueue($message, $queue);
    }

    /**
     * Clear all messages of the queue/tube
     *
     * ```php
     * <?php
     * $I->clearQueue('default');
     * ?>
     * ```
     *
     * @param string $queue Queue Name
     */
    public function clearQueue($queue)
    {
        $this->queueDriver->clearQueue($queue);
    }

    // ----------- GRABBER METHODS BELOW HERE -----------------------//

    /**
     * Grabber method to get the list of queues/tubes on the server
     *
     * ```php
     * <?php
     * $queues = $I->grabQueues();
     * ?>
     * ```
     *
     * @return array List of Queues/Tubes
     */
    public function grabQueues()
    {
        return $this->queueDriver->getQueues();
    }

    /**
     * Grabber method to get the current number of messages on the queue/tube (pending/ready)
     *
     * ```php
     * <?php
     *     $I->grabQueueCurrentCount('default');
     * ?>
     * ```
     * @param string $queue Queue Name
     *
     * @return int Count
     */
    public function grabQueueCurrentCount($queue)
    {
        return $this->queueDriver->getMessagesCurrentCountOnQueue($queue);
    }

    /**
     * Grabber method to get the total number of messages on the queue/tube
     *
     * ```php
     * <?php
     *     $I->grabQueueTotalCount('default');
     * ?>
     * ```
     *
     * @param $queue Queue Name
     *
     * @return int Count
     */
    public function grabQueueTotalCount($queue)
    {
        return $this->queueDriver->getMessagesTotalCountOnQueue($queue);
    }
}
