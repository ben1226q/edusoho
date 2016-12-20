<?php
namespace Biz\Task\Event;

use Codeages\Biz\Framework\Event\Event;
use Codeages\PluginBundle\Event\EventSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActivitySubscriber extends EventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'activity.start'    => 'onActivityStart',
            'activity.doing'    => 'onActivityDoing',
            'activity.operated' => 'onActivityOperated'
        );
    }

    public function onActivityOperated(Event $event)
    {
        if(!$event->hasArgument('taskId')) {
            return;
        }
        $taskId = $event->getArgument('taskId');

        if(!empty($taskId) && $this->getTaskService()->isFinished($taskId)) {
            $this->getTaskService()->finishTaskResult($taskId);
        }
    }

    public function onActivityStart(Event $event)
    {
        $task = $event->getArgument('task');
        $this->getTaskService()->startTask($task['id']);
    }

    public function onActivityDoing(Event $event)
    {
        $taskId = $event->getArgument('taskId');

        if (!$event->hasArgument('timeStep')) {
            $time = TaskService::LEARN_TIME_STEP;
        } else {
            $time = $event->getArgument('timeStep');
        }

        if (empty($taskId)) {
            return;
        }

        $this->getTaskService()->doTask($taskId, $time);
    }

    protected function getTaskService()
    {
        return $this->createService('Task:TaskService');
    }

    protected function getTaskResultService()
    {
        return $this->createService('Task:TaskResultService');
    }
}
