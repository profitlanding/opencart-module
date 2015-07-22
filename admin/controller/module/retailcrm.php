<?php

require_once DIR_SYSTEM . 'library/retailcrm.php';

class ControllerModuleRetailcrm extends Controller {
    private $error = array();
    protected $log, $statuses, $payments, $deliveryTypes, $retailcrm;
    public $children, $template;

    public function install() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting(
            'retailcrm',
            array('retailcrm_status' => 1)
        );
    }

    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting(
            'retailcrm',
            array('retailcrm_status' => 0)
        );
    }

    public function index() {

        $this->load->model('setting/setting');
        $this->load->model('setting/extension');
        $this->load->model('retailcrm/references');
        $this->load->language('module/retailcrm');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addStyle('/admin/view/stylesheet/retailcrm.css');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('retailcrm', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->redirect($this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'));
        }

        $text_strings = array(
            'heading_title',
            'text_enabled',
            'text_disabled',
            'button_save',
            'button_cancel',
            'text_notice',
            'retailcrm_url',
            'retailcrm_apikey',
            'retailcrm_base_settings',
            'retailcrm_dict_settings',
            'retailcrm_dict_delivery',
            'retailcrm_dict_status',
            'retailcrm_dict_payment',
        );

        foreach ($text_strings as $text) {
            $this->data[$text] = $this->language->get($text);
        }

        $this->data['retailcrm_errors'] = array();
        $this->data['saved_settings'] = $this->model_setting_setting->getSetting('retailcrm');

        if (
            !empty($this->data['saved_settings']['retailcrm_url'])
            &&
            !empty($this->data['saved_settings']['retailcrm_apikey'])
        ) {

            $this->retailcrm = new ApiHelper(
                $this->data['saved_settings']['retailcrm_url'],
                $this->data['saved_settings']['retailcrm_apikey']
            );

            /*
             * Delivery
             */
            $this->deliveryTypes = array();

            try {
                $this->deliveryTypes = $this->retailcrm->deliveryTypesList();
            } catch (CurlException $e) {
                $this->data['retailcrm_error'][] = $e->getMessage();
                $this->log->addError('RestApi::deliveryTypesList::Curl:' . $e->getMessage());
            } catch (InvalidJsonException $e) {
                $this->data['retailcrm_error'][] = $e->getMessage();
                $this->log->addError('RestApi::deliveryTypesList::JSON:' . $e->getMessage());
            }

            $this->data['delivery'] = array(
                'opencart' => $this->model_retailcrm_tools->getOpercartDeliveryMethods(),
                'retailcrm' => $this->deliveryTypes
            );

            /*
             * Statuses
             */
            $this->statuses = array();

            try {
                $this->statuses = $this->retailcrm->orderStatusesList();
            } catch (CurlException $e) {
                $this->data['retailcrm_error'][] = $e->getMessage();
                $this->log->addError('RestApi::orderStatusesList::Curl:' . $e->getMessage());
            } catch (InvalidJsonException $e) {
                $this->data['retailcrm_error'][] = $e->getMessage();
                $this->log->addError('RestApi::orderStatusesList::JSON:' . $e->getMessage());
            }

            $this->data['statuses'] = array(
                'opencart' => $this->model_retailcrm_tools->getOpercartOrderStatuses(),
                'retailcrm' => $this->statuses
            );

            /*
             * Payment
             */
            $this->payments = array();

            try {
                $this->payments = $this->retailcrm->paymentTypesList();
            } catch (CurlException $e) {
                $this->data['retailcrm_error'][] = $e->getMessage();
                $this->log->addError('RestApi::paymentTypesList::Curl:' . $e->getMessage());
            } catch (InvalidJsonException $e) {
                $this->data['retailcrm_error'][] = $e->getMessage();
                $this->log->addError('RestApi::paymentTypesList::JSON:' . $e->getMessage());
            }

            $this->data['payments'] = array(
                'opencart' => $this->model_retailcrm_tools->getOpercartPaymentTypes(),
                'retailcrm' => $this->payments
            );

        }

        $config_data = array(
            'retailcrm_status'
        );

        foreach ($config_data as $conf) {
            if (isset($this->request->post[$conf])) {
                $this->data[$conf] = $this->request->post[$conf];
            } else {
                $this->data[$conf] = $this->config->get($conf);
            }
        }

        if (isset($this->error['warning'])) {
            $this->data['error_warning'] = $this->error['warning'];
        } else {
            $this->data['error_warning'] = '';
        }

        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_home'),
            'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );

        $this->data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_module'),
            'href'      => $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $this->data['breadcrumbs'][] = array(
            'text'      => $this->language->get('heading_title'),
            'href'      => $this->url->link('module/retailcrm', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $this->data['action'] = $this->url->link('module/retailcrm', 'token=' . $this->session->data['token'], 'SSL');

        $this->data['cancel'] = $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL');


        $this->data['modules'] = array();

        if (isset($this->request->post['retailcrm_module'])) {
            $this->data['modules'] = $this->request->post['retailcrm_module'];
        } elseif ($this->config->get('retailcrm_module')) {
            $this->data['modules'] = $this->config->get('retailcrm_module');
        }

        $this->load->model('design/layout');

        $this->data['layouts'] = $this->model_design_layout->getLayouts();

        $this->template = 'module/retailcrm.tpl';
        $this->children = array(
            'common/header',
            'common/footer',
        );

        $this->response->setOutput($this->render());
    }

    public function history()
    {
        $this->load->model('retailcrm/history');
        $this->model_retailcrm_history->request();
    }

    public function icml()
    {
        $this->load->model('retailcrm/icml');
        $this->model_retailcrm_icml->generateICML();
    }

    private function validate() {
        if (!$this->user->hasPermission('modify', 'module/retailcrm')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->error) {
            return TRUE;
        } else {
            return FALSE;
        }
    }


}
