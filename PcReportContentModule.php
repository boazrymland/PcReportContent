<?php

/**
 * PcReportContent module - enables reporting of 'inappropriate content' on your website.
 *
 * This version is a rewrite of tha awfully designed previous version of the extension.
 */
class PcReportContentModule extends CWebModule
{
    /**
     * Max report message length
     */
    const MAX_REPORT_MSG_LENGTH = 250;

    /**
     * Where to send emails to? Update as you see fit...
     */
    public $targetEmail;

    public function init()
    {
        // this method is called when the module is being created
        // you may place code here to customize the module or the application

        // if targetEmail is empty assign it the default value
        if (empty($this->targetEmail)) {
            $this->targetEmail = 'admin@' . Yii::app()->request->serverName;
        }
    }

    public function beforeControllerAction($controller, $action)
    {
        if (parent::beforeControllerAction($controller, $action)) {
            // this method is called before any module controller action is performed
            // you may place customized code here
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns an HTML template for 'report inappropriate content' submission, that can be utilized whereever needed. Its needed
     * in use cases such as comments, where we can have hundreds of same entities of 'report inappropriate' widgets in one page (for
     * each comment). Instead of actual rendering of this widget for each, which can be very expensive, this method returns the almost
     * complete HTML template where client side can 'plant' on demand, only replacing the model-id in the template. The model id string
     * placeholder is denoted by the string MID.
     *
     * @param string $model_name the class name the report should be made on. This *is* known in advance where mass rendering is needed
     *                  - unlike the model id, which is the varying factor.
     * @return string the resulting html
     */
    public static function getReportFormTemplate($model_name)
    {
        $widget = new PcReportContentWidget(array('modelClassName' => $model_name));
        return $widget->render('basic_report_content', array('model_class_name' => $model_name, 'model_id' => 'MID'), true);
    }

    /**
     * Method for actual report generation. By default, it sends an email with the report details. If you need some other kind of
     * report mechanism (DB saving, etc) this is the place to edit.
     *
     * @param string $model_name the inappropriate content model class name.
     * @param integer $model_id the inappropriate content model object id.
     * @param string $message the actual report message
     * @param string $referrer the url on which the report occurred.
     * @param integer $reporter_uid the reporting user id
     * @param string $reporter_username the reporting user username
     *
     * @return bool nothing success (true) or failure (false) in performing the actual report.
     */
    public static function reportContent($model_name, $model_id, $message, $referrer, $reporter_uid, $reporter_username)
    {
        // prepare reported-content-author-id (and username).
        $content_object_author_id = call_user_func(array($model_name, 'getCreatorUserId'), $model_id);
        /* @var PcBaseArModel $content_object */
        /* @var PcBaseArModel $model_name */
        $content_object = $model_name::model()->findByPk($model_id);
        $author_relation_name = $content_object->getCreatorRelationName();
        $content_object_author_username = $content_object->$author_relation_name->username;

        // I have everything I need. Now prepare the message
        $msg = <<<EOD
Hello!
<br />
<br />
'Inappropriate content' has just been reported.<br />
<br />
Here are the details:<br />
-------<br />
URL where report has been submitted: $referrer<br />
Content model-name: $model_name<br />
Content id: $model_id<br />
Original content author id: $content_object_author_id<br />
Original content author username: $content_object_author_username<br />
Reporter user name: $reporter_username<br />
Reporter user id: $reporter_uid<br />
<br />
Report message:<br />
------ REPORT MESSAGE ------<br />
$message<br />
------ REPORT MESSAGE ------<br />
EOD;

        // now send in an email
        // this is the part that you might wanna update should you NOT use the email component I use below (or not use email report at all...).
        $report_email = new YiiMailMessage();
        $report_email->setBody($msg, 'text/html');
        $report_email->subject = "Inappropriate content reported: $model_name, id=$model_id";
        $report_email->addTo(self::REPORTS_TARGET_EMAIL);
        $report_email->from = "content-report@" . Yii::app()->request->serverName;
        /* @var YiiMail Yii::app()->mail */
        Yii::app()->mail->send($report_email);
        return true;
    }
}
