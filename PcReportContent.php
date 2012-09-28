<?php
/**
 * PcReportContent.php
 *
 * @license:
 * Copyright (c) 2012, Boaz Rymland
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
 * following conditions are met:
 * - Redistributions of source code must retain the above copyright notice, this list of conditions and the following
 *      disclaimer.
 * - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following
 *      disclaimer in the documentation and/or other materials provided with the distribution.
 * - The names of the contributors may not be used to endorse or promote products derived from this software without
 *      specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 * EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
class PcReportContent extends CWidget {
	const REPORT_CONTENT_AJAX_NOTIFIER = "PcReportContentAjaxSubmission";
	// used for storing moderator email address (per widget instantiation) in persistent storage
	const MODERATOR_EMAIL_PERSISTENT_STORAGE_PREFIX = __CLASS__;

	/* @var string $moderatorEmailAddress the email address to which to send the report being reported 'now' (this can
	 * change for each instantiation of this widget. */
	public $moderatorEmailAddress;

	/* @var string $modelClassName holds the class name 'this' instance of widget refers to and should report on. see also $modelClassId */
	public $modelClassName;

	/* @var string $modelClassId holds the model id 'this' instance of widget refers to and should report on. see also $modelClassName */
	public $modelClassId;

	/* @var int $maxReportLength number of chars the report should be limited to. Automatic trimming will occur to make the report at this max length */
	public $maxReportLength = 250;

	/* @var bool whether the widget will enforce supplement of $moderatorEmailAddress in each instantiation of it or not.
	 * If not, Yii::app()->params['PcReportContentDefaultModeratorAddress'] will be used. Default is NOT. Note that this should
	 * be overriden in Yii::app()->params['PcReportContentForceModeratorAddress'] app parameter. */
	private $_forceModeratorAddress = false;

	private $_isInitialized = false;

	/**
	 * init...
	 */
	public function init() {
		// determine if we force this instantiation to supply moderator email address or not.
		// this is only handled during rendering of the widget, not(!) when its processed.
		if ((!Yii::app()->request->isPostRequest) && (!Yii::app()->request->isPostRequest)) {
			if (!isset($this->moderatorEmailAddress)) {
				// only relevant if moderatorEmailAddress is not set at all...
				if ((isset(Yii::app()->params['PcReportContentForceModeratorAddress'])) && (Yii::app()->params['PcReportContentForceModeratorAddress'] === true)) {
					/* According to general configuration this widget should ALWAYS be initialized with enforcement of moderator address.
									 * Just log this and 'return' in run() nothing will happen as this widget is 'not initialized' */
					Yii::log('No moderator email address provided but this widget is being run with Yii::app()->params[PcReportContentForceModeratorAddress] = true', CLogger::LEVEL_WARNING, __METHOD__);
					return;
				}
				else {
					// Determine the default moderator address and use it.
					if (isset(Yii::app()->params['PcReportContentDefaultModeratorAddress'])) {
						$this->moderatorEmailAddress = Yii::app()->params['PcReportContentDefaultModeratorAddress'];
					}
					else {
						$this->moderatorEmailAddress = Yii::app()->params['adminEmail'];
					}
				}
			}
			$this->_setModeratorEmailPersistently($this->moderatorEmailAddress);
		}
		$this->_isInitialized = true;
	}

	/**
	 * run method
	 *
	 * @return mixed
	 */
	public function run() {
		if (!$this->_isInitialized) {
			Yii::log("Not initialized. Ending execution...", CLogger::LEVEL_INFO, __METHOD__);
		}

		/*
		 * handle this ajax request only if its submitted to this widget (and not to some other widget, for example
		 */
		if ((Yii::app()->request->getParam(self::REPORT_CONTENT_AJAX_NOTIFIER)) && (Yii::app()->request->isAjaxRequest)) {
			// first, see if the user has permission to submit this report
			if (!Yii::app()->user->checkAccess('report inappropriate content')) {
				$login_url = Yii::app()->user->loginUrl[0];
				echo Yii::t("PcReportContent.general", "Please {register} or {login} to report inappropriate content.",
					array(
						'{register}' => CHtml::link(Yii::t("PcReportContent.general", "register"), array(Yii::app()->params['registrationRoute'])),
						'{login}' => CHtml::link(Yii::t("PcReportContent.general", "login"), array(Yii::app()->user->loginUrl[0]))
					)
				);
				return;
			}

			/* process report - we do this in a separate function since its a nicer design (cohesion, 'single responsibility', etc...) */
			echo $this->_processReport();
			return;
		}
		else if (Yii::app()->request->isAjaxRequest || Yii::app()->request->isPostRequest) {
			// some other AJAX or POST request. do nothing.
			return;
		}
		else {
			// render the widget
			$this->render('basic_report_content',
				array(
					'model_class_name' => $this->modelClassName,
					'model_id' => $this->modelClassId,
					'ajax_submit_parameter' => self::REPORT_CONTENT_AJAX_NOTIFIER,
				)
			);
		}
	}

	/**
	 * Stores in persistent storage the email address 'this' specific instant of 'report inapp cotent' widget was instantiated for.
	 * For now, store in user's session.
	 *
	 * We need this since when the user submits the 'report content' form, we have no way to know what moderator email address
	 * was set for it. Its set during render time, in the specific view file in which this widget is rendered in (and thus achieves
	 * custom moderator per view file). But during submission, since we do not wish to put the moderator address in the form
	 * itself to be submitted along with the report itself, we store it in some persistent storage (user's session) and when
	 * processing a submission we fetch it back.
	 *
	 * We could have stored the email address in the form itself, encrypted in some way but i preferred not to encrypt-decrypt
	 * on a possibly high frequency and also encryption can be hacked. Luckily for you :) I have designed the code to be easily modify-able to allow
	 * for easier customization/change of this. Simply edit this method and its 'get' counterpart (see 'see' tag here).
	 *
	 * @see _getStoredModeratorEmail for the 'get' counterpart method of this method.
	 * @param string $address
	 */
	private function _setModeratorEmailPersistently($address) {
		Yii::app()->user->setState(
			self::MODERATOR_EMAIL_PERSISTENT_STORAGE_PREFIX . "__" . $this->modelClassName . "-" . $this->modelClassId . "-moderator_email_address",
			$address
		);
	}

	/**
	 * Returns the 'persistently' stored moderator email address for 'this' instance of 'report inapp content' widget.
	 *
	 * @see _setModeratorEmailPersistently for the 'set' counterpart of this method.
	 *
	 * @return string
	 */
	private function _getStoredModeratorEmail() {
		return Yii::app()->user->getState(self::MODERATOR_EMAIL_PERSISTENT_STORAGE_PREFIX . "__" . $this->modelClassName . "-" . $this->modelClassId . "-moderator_email_address");
	}

	/**
	 * Returns the content creator username
	 * Uses CActiveRecord caching capabilities for better performance.
	 */
	private function _getContentCreatorUserName() {
		/* @var CActiveRecord $obj */
		$user_relation_name = call_user_func(array($this->modelClassName, 'getCreatorRelationName'));
		$obj = call_user_func(array($this->modelClassName, 'model'));
		// cache it for 3 hours
		$record = $obj->cache(60 * 60 * 3)->with($user_relation_name)->findByPk($this->modelClassId);
		return $record->$user_relation_name->username;
	}

	/**
	 * Returns the content creator user id
	 *
	 * getCreatorRelationName() is in PcBaseArModel class. Make sure its implemented in your class.
	 */
	private function _getContentCreatorUserId() {
		/* @var CActiveRecord $obj */
		$user_relation_name = call_user_func(array($this->modelClassName, 'getCreatorRelationName'));
		$obj = call_user_func(array($this->modelClassName, 'model'));
		// cache it for 3 hours
		$record = $obj->cache(60 * 60 * 3)->with($user_relation_name)->findByPk($this->modelClassId);
		return $record->$user_relation_name->primaryKey;
	}

	/**
	 * This method is responsible to actually do something with the submitted report.
	 * For now, we're required to send an email with the details of the report in the email itself.
	 * Return with success/error message that is sent directly to the user.
	 *
	 * @return string
	 */
	private function _processReport() {
		/*
		 * what does it need to compose its email?
		 * - moderator email address.
		 * - reason text
		 * - model class name
		 * - model id
		 * - model original creator
		 * - URL where this report has been submitted from
		 * - reporter user name + id
		 */
		if (mb_strlen(Yii::app()->request->getParam('rc-' . strtolower($this->modelClassName) . "-" . $this->modelClassId)) > $this->maxReportLength) {
			$reason = mb_substr(Yii::app()->request->getParam('rc-' . strtolower($this->modelClassName) . "-" . $this->modelClassId), 0, $this->maxReportLength) .
					"...<br />Message trimmed to the max allowed number of character - " . $this->maxReportLength . "<br />";
		}
		else {
			$reason = Yii::app()->request->getParam('rc-' . strtolower($this->modelClassName) . "-" . $this->modelClassId);
		}
		$refering_url = trim(Yii::app()->request->urlReferrer);
		$reporter_user_name = Yii::app()->user->name;
		$reporter_user_id = Yii::app()->user->id;
		$content_creator_user_id = $this->_getContentCreatorUserId();
		$content_creator_user_name = $this->_getContentCreatorUserName();
		$report_email = new YiiMailMessage();
		$msg = <<<EOM
Hello!<br />
<br />
A report of 'inappropriate content' has just landed on my table and doing as you requested, i'm passing it to you.<br />
Here are the details:<br />
URL where report has been submitted: $refering_url.<br />
Content name (model class name): $this->modelClassName.<br />
Content id (model primary key): $this->modelClassId.<br />
Original content creator id:$content_creator_user_id<br />
Original content creator username:$content_creator_user_name<br />
Reporter user name: $reporter_user_name.<br />
Reporter user id: $reporter_user_id.<br />
Actual report: (below...)<br />
------ REPORT ------<br />
$reason<br />
------ REPORT ------
<br />
Enjoy handling!<br />
Your humble web site.<br />
EOM;
		$report_email->setBody($msg, 'text/html');
		$report_email->subject = "Inappropriate content reported (on '$this->modelClassName', id=$this->modelClassId)";
		$report_email->addTo($this->_getStoredModeratorEmail());
		$report_email->from = "inapp-content@" . Yii::app()->request->serverName; //Yii::app()->params['adminEmail'];
		/* @var YiiMail Yii::app()->mail */
		Yii::app()->mail->send($report_email);
		return Yii::t("PcReportContent.general", "Report submitted successfully!");
	}

	/**
	 * overriding default 'actions()' method
	 *
	 * @return array
	 */
	public static function actions() {
		return array(
			'ProcessReportContent' => 'ext.PcReportContent.actions.ProcessReportContent',
		);
	}
}
