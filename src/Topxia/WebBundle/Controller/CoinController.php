<?php

namespace Topxia\WebBundle\Controller;

use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;
use Topxia\WebBundle\Controller\BaseController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CoinController extends BaseController
{
    public function indexAction(Request $request)
    {
        $user = $this->getCurrentUser();

        if (!$user->isLogin()) {
            return $this->createMessageResponse('error', '用户未登录，请先登录！');
        }

        $coinEnabled = $this->setting("coin.coin_enabled");

        if (empty($coinEnabled) || $coinEnabled == 0) {
            return $this->createMessageResponse('error', '网校虚拟币未开启！');
        }

        $account = $this->getCashAccountService()->getAccountByUserId($user->id, true);

        $chargeCoin = $this->getAppService()->findInstallApp('ChargeCoin');

        if (empty($account)) {
            $this->getCashAccountService()->createAccount($user->id);
        }

        $fields     = $request->query->all();
        $conditions = array();

        if (!empty($fields)) {
            $conditions = $fields;
        }

        $conditions['cashType'] = 'Coin';
        $conditions['userId']   = $user->id;

        $conditions['startTime'] = 0;
        $conditions['endTime']   = time();

        switch ($request->get('lastHowManyMonths')) {
            case 'oneWeek':
                $conditions['startTime'] = $conditions['endTime'] - 7 * 24 * 3600;
                break;
            case 'twoWeeks':
                $conditions['startTime'] = $conditions['endTime'] - 14 * 24 * 3600;
                break;
            case 'oneMonth':
                $conditions['startTime'] = $conditions['endTime'] - 30 * 24 * 3600;
                break;
            case 'twoMonths':
                $conditions['startTime'] = $conditions['endTime'] - 60 * 24 * 3600;
                break;
            case 'threeMonths':
                $conditions['startTime'] = $conditions['endTime'] - 90 * 24 * 3600;
                break;
        }

        $paginator = new Paginator(
            $this->get('request'),
            $this->getCashService()->searchFlowsCount($conditions),
            20
        );

        $cashes = $this->getCashService()->searchFlows(
            $conditions,
            array('ID', 'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $conditions['type'] = 'inflow';
        $amountInflow       = $this->getCashService()->analysisAmount($conditions);

        $conditions['type'] = 'outflow';
        $amountOutflow      = $this->getCashService()->analysisAmount($conditions);

        // $amount=$this->getOrderService()->analysisAmount(array('userId'=>$user->id,'status'=>'paid'));
        // $amount+=$this->getCashOrdersService()->analysisAmount(array('userId'=>$user->id,'status'=>'paid'));
        return $this->render('TopxiaWebBundle:Coin:index.html.twig', array(
            'payments'      => $this->getEnabledPayments(),
            'account'       => $account,
            'cashes'        => $cashes,
            'paginator'     => $paginator,
            // 'amount'=>$amount,
            'ChargeCoin'    => $chargeCoin,
            'amountInflow'  => $amountInflow ?: 0,
            'amountOutflow' => $amountOutflow ?: 0
        ));
    }

    public function cashBillAction(Request $request)
    {
        $user = $this->getCurrentUser();

        $conditions = array(
            'userId' => $user['id']
        );

        $conditions['cashType']  = 'RMB';
        $conditions['startTime'] = 0;
        $conditions['endTime']   = time();

        switch ($request->get('lastHowManyMonths')) {
            case 'oneWeek':
                $conditions['startTime'] = $conditions['endTime'] - 7 * 24 * 3600;
                break;
            case 'twoWeeks':
                $conditions['startTime'] = $conditions['endTime'] - 14 * 24 * 3600;
                break;
            case 'oneMonth':
                $conditions['startTime'] = $conditions['endTime'] - 30 * 24 * 3600;
                break;
            case 'twoMonths':
                $conditions['startTime'] = $conditions['endTime'] - 60 * 24 * 3600;
                break;
            case 'threeMonths':
                $conditions['startTime'] = $conditions['endTime'] - 90 * 24 * 3600;
                break;
        }

        $paginator = new Paginator(
            $request,
            $this->getCashService()->searchFlowsCount($conditions),
            20
        );

        $cashes = $this->getCashService()->searchFlows(
            $conditions,
            array('ID', 'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );
        $conditions['type'] = 'inflow';
        $amountInflow       = $this->getCashService()->analysisAmount($conditions);

        $conditions['type'] = 'outflow';
        $amountOutflow      = $this->getCashService()->analysisAmount($conditions);

        return $this->render('TopxiaWebBundle:Coin:cash_bill.html.twig', array(
            'cashes'        => $cashes,
            'paginator'     => $paginator,
            'amountInflow'  => $amountInflow ?: 0,
            'amountOutflow' => $amountOutflow ?: 0

        ));
    }

    public function changeAction(Request $request)
    {
        $user   = $this->getCurrentUser();
        $userId = $user->id;

        $change = $this->getCashAccountService()->getChangeByUserId($userId);

        if (empty($change)) {
            $change = $this->getCashAccountService()->addChange($userId);
        }

        $amount = $this->getOrderService()->analysisAmount(array('userId' => $user->id, 'status' => 'paid'));
        $amount += $this->getCashOrdersService()->analysisAmount(array('userId' => $user->id, 'status' => 'paid'));

        $changeAmount = $amount - $change['amount'];

        list($canUseAmount, $canChange, $data) = $this->caculate($changeAmount, 0, array());

        if ($request->getMethod() == "POST") {
            if ($canChange > 0) {
                $this->getCashAccountService()->changeCoin($changeAmount - $canUseAmount, $canChange, $userId);
            }

            return $this->redirect($this->generateUrl('my_coin'));
        }

        return $this->render('TopxiaWebBundle:Coin:coin-change-modal.html.twig', array(
            'amount'       => $amount,
            'changeAmount' => $changeAmount,
            'canChange'    => $canChange,
            'canUseAmount' => $canUseAmount,
            'data'         => $data
        ));
    }

    public function showAction(Request $request)
    {
        $coinSetting = $this->getSettingService()->get('coin', array());

        if (isset($coinSetting['coin_content'])) {
            $content = $coinSetting['coin_content'];
        } else {
            $content = '';
        }

        return $this->render('TopxiaWebBundle:Coin:coin-content-show.html.twig', array(
            'content'     => $content,
            'coinSetting' => $coinSetting
        ));
    }

    protected function caculate($amount, $canChange, $data)
    {
        $coinSetting = $this->getSettingService()->get('coin', array());

        $coinRanges = $coinSetting['coin_consume_range_and_present'];

        if ($coinRanges == array(array(0, 0))) {
            return array($amount, $canChange, $data);
        }

        for ($i = 0; $i < count($coinRanges); $i++) {
            $consume = $coinRanges[$i][0];
            $change  = $coinRanges[$i][1];

            foreach ($coinRanges as $key => $range) {
                if ($change == $range[1] && $consume > $range[0]) {
                    $consume = $range[0];
                }
            }

            $ranges[] = array($consume, $change);
        }

        $ranges = ArrayToolkit::index($ranges, 1);

        $send          = 0;
        $bottomConsume = 0;

        foreach ($ranges as $key => $range) {
            if ($amount >= $range[0] && $send < $range[1]) {
                $send = $range[1];
            }

            if ($bottomConsume > $range[0] || $bottomConsume == 0) {
                $bottomConsume = $range[0];
            }
        }

        if (isset($ranges[$send]) && $amount >= $ranges[$send][0]) {
            $canUseAmount = $amount - $ranges[$send][0];
            $canChange += $send;
        } else {
            $canUseAmount = $amount;
            $canChange += $send;
        }

        if ($send > 0) {
            $data[] = array(
                'send'       => "消费满{$ranges[$send][0]}元送{$ranges[$send][1]}",
                'sendAmount' => "{$ranges[$send][1]}");
        }

        if ($canUseAmount >= $bottomConsume) {
            list($canUseAmount, $canChange, $data) = $this->caculate($canUseAmount, $canChange, $data);
        }

        return array($canUseAmount, $canChange, $data);
    }

    public function payAction(Request $request)
    {
        $formData           = $request->request->all();
        $user               = $this->getCurrentUser();
        $formData['userId'] = $user['id'];

        $order = $this->getCashOrdersService()->addOrder($formData);
        return $this->redirect($this->generateUrl('pay_center_show', array(
            'sn'         => $order['sn'],
            'targetType' => $order['targetType']
        )));
    }

    public function resultNoticeAction(Request $request)
    {
        return $this->render('TopxiaWebBundle:Coin:retrun-notice.html.twig');
    }

    protected function getEnabledPayments()
    {
        $enableds = array();

        $setting = $this->setting('payment', array());

        if (empty($setting['enabled'])) {
            return $enableds;
        }

        $payNames = array('alipay');

        foreach ($payNames as $payName) {
            if (!empty($setting[$payName.'_enabled'])) {
                $enableds[$payName] = array(
                    'type' => empty($setting[$payName.'_type']) ? '' : $setting[$payName.'_type']
                );
            }
        }

        return $enableds;
    }

    protected function getCashService()
    {
        return $this->getServiceKernel()->createService('Cash.CashService');
    }

    protected function getCashAccountService()
    {
        return $this->getServiceKernel()->createService('Cash.CashAccountService');
    }

    protected function getCashOrdersService()
    {
        return $this->getServiceKernel()->createService('Cash.CashOrdersService');
    }

    protected function getOrderService()
    {
        return $this->getServiceKernel()->createService('Order.OrderService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getAppService()
    {
        return $this->getServiceKernel()->createService('CloudPlatform.AppService');
    }
}
