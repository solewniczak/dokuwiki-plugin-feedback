<?php
/**
 * DokuWiki Plugin feedback (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_feedback extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'handle_start');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax');

    }

    /**
     * Add some info about the current page to the info array if feedback can be given
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_start(Doku_Event &$event, $param) {
        global $ID;
        global $JSINFO;

        // allow anonymous feedback?
        if(!$_SERVER['REMOTE_USER'] && !$this->getConf('allowanon')) return;
        // any contact defined?
        if(!$this->getFeedbackContact($ID)) return;

        $JSINFO['plugin_feedback'] = true;
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_ajax(Doku_Event &$event, $param) {
        // our event?
        if($event->data != 'plugin_feedback') return;
        $event->preventDefault();
        $event->stopPropagation();

        // allow anonymous feedback?
        if(!$_SERVER['REMOTE_USER'] && !$this->getConf('allowanon')) {
            http_status(400);
            die('no anonymous access');
        }

        // get submitted data
        global $INPUT;
        $id = $INPUT->str('id');
        $feedback = $INPUT->str('feedback');

        // get the responsible contact
        $contact = $this->getFeedbackContact($id);
        if(!$contact) {
            http_status(400);
            die('no contact defined');
        }

        // get info on user
        $user = null;
        if($_SERVER['REMOTE_USER']) {
            /** @var DokuWiki_Auth_Plugin $auth */
            global $auth;
            $user = $auth->getUserData($_SERVER['REMOTE_USER']);
            if(!$user) $user = null;
        }

        // send the mail
        $mailer = new Mailer();
        $mailer->to($contact);
        if($user) $mailer->setHeader('Reply-To', $user['mail']);
        $mailer->subject($this->getLang('subject'));
        $mailer->setBody(
            io_readFile($this->localFN('mail')),
            array('PAGE' => $id, 'FEEDBACK' => $feedback)
        );
        $mailer->send();


        header('Content-Type: text/html; charset=utf-8');
        echo $this->getLang('thanks');
    }

    /**
     * Get the responsible contact for givven ID
     *
     * @param $id
     * @return false|string
     */
    public function getFeedbackContact($id) {
        $conf = confToHash(DOKU_CONF . 'plugin_feedback.conf');

        while($ns = getNS($id)) {
            if(isset($conf[$ns])) return $conf[$ns];
        }

        return false;
    }

    /**
     * prints or returns the the action link
     *
     * Alternatively you can add the plugin_feedback class to any object in the DOM and it will be used
     * for triggering the feedback dialog
     *
     * @param bool $return
     * @return string
     */
    public function tpl($return = false) {
        global $ID;

        // allow anonymous feedback?
        if(!$_SERVER['REMOTE_USER'] && !$this->getConf('allowanon')) return;
        // any contact defined?
        if(!$this->getFeedbackContact($ID)) return;

        $html = '<a href="#" class="plugin_feedback">' . $this->getLang('feedback') . '</a>';
        if($return) return $html;
        echo $html;
        return '';
    }

}

// vim:ts=4:sw=4:et: