<?php

namespace Topxia\Api\Resource;

use Silex\Application;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;

class ChaosThreadsPosts extends BaseResource
{
    public function post(Application $app, Request $request)
    {
        $fields = $request->request->all();

        if (!ArrayToolkit::requireds($fields, array('threadType'))) {
            return array('message' => '缺少必填字段threadType');
        }

        switch ($fields['threadType']) {
            case 'common':

                if (!ArrayToolkit::requireds($fields, array('parentId'))) {
                    return array('message' => '缺少必填字段');
                }

                $fields = ArrayToolkit::parts($fields, array('threadId', 'parentId', 'content'));
                $post   = $this->getThreadService()->createPost($fields);
                break;

            case 'course':

                if (!ArrayToolkit::requireds($fields, array('courseId', 'content', 'threadId'))) {
                    return array('message' => '缺少必填字段');
                }

                $fields = ArrayToolkit::parts($fields, array('threadId', 'content', 'courseId'));
                $post   = $this->getCourseThreadService()->createPost($fields);
                break;

            case 'group':
                $currentUser = $this->getCurrentUser();

                if (!ArrayToolkit::requireds($fields, array('threadId', 'content', 'groupId'))) {
                    return array('message' => '缺少必填字段');
                }

                $fields['userId'] = $currentUser['id'];
                $fields['postId'] = isset($fields['postId']) ? $fields['postId'] : 0;
                $fields           = ArrayToolkit::parts($fields, array('content', 'groupId', 'userId', 'threadId', 'postId'));
                $postContent      = array(
                    'content'    => $fields['content'],
                    'fromUserId' => 0
                );

                $post = $this->getGroupThreadService()->postThread($postContent, $fields['groupId'], $fields['userId'], $fields['threadId'], $fields['postId']);
                break;

            default:
                return array('message' => 'threadType类型不正确');
                break;
        }

        return $this->filter($post);
    }

    public function getThreadPosts(Application $app,Request $request){
        $currentUser = $this->getCurrentUser();
        $start       = $request->query->get('start', 0);
        $limit       = $request->query->get('limit', 10);

        $conditions  = array(
            'userId' => $currentUser['id']
        );

        $total       = $this->getCourseThreadService()->searchThreadPostsCount($conditions);
        $start       = $start == -1 ? rand(0, $total - 1) : $start;  

        $posts       = $this->getCourseThreadService()->searchThreadPosts($conditions,null,$start,$limit);

        $threadPosts = array();

        foreach ($posts as $post) {            
            $course                          = $this->getCourseService()->getCourse($post['courseId']);

            if(isset($course['userId']) && $course==$currentUser['id']){
                continue;
            }
            if(!empty($course)){
                $threadPosts['courseId']     = $post['courseId'];
                $threadPosts['courseTitle']  = $course['title'];
                $threadPosts['smallPicture'] = $course['smallPicture'];
                $threadPosts['middlePicture']= $course['middlePicture'];
                $threadPosts['lagerPicture'] = $course['lagerPicture'];
            }
            $thread                          = $this->getCourseThreadService()->getThread(null,$post['threadId']);
            if(!empty($thread)){
                $threadPosts['title']        = $thread['title'];
            }
            $threadPosts['id']               = $post['id'];            
            $threadPosts['content']          = $post['content'];
            $threadPosts['createdTime']      = $post['createdTime'];
        }
        return $threadPosts;
    }

    public function filter(&$res)
    {
        $res['createdTime'] = date('c', $res['createdTime']);
        return $res;
    }

    protected function getThreadService()
    {
        return $this->getServiceKernel()->createService('Thread.ThreadService');
    }

    protected function getCourseThreadService()
    {
        return $this->getServiceKernel()->createService('Course.ThreadService');
    }

    protected function getGroupThreadService()
    {
        return $this->getServiceKernel()->createService('Group.ThreadService');
    }

    protected function getCourseService(){
        return $this->getServiceKernel()->createService('Course.CourseService');
    }
}
