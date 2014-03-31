<?php

/**
 * ReportContentAjaxProcessor class
 *
 * This class processes AJAX request
 *
 * @author: Boaz Rymland
 * 30-03-2014
 */
class ReportContentProcessorController extends Controller
{
    /**
     * Process reports of inappropriate content
     * requires the following parameters:
     *
     */
    public function actionProcessReport()
    {
        // validate user is allowed to report
        if (Yii::app()->user->isGuest) {
            $this->_finishWithError(
                Yii::t(
                    'PcReportContentModule.general',
                    'Please {register} or {login} first',
                    array(
                        '{register}' => CHtml::link(
                                Yii::t("PcReportContentModule.general", "register"),
                                array(Yii::app()->params['registrationRoute'])
                            ),
                        '{login}' => CHtml::link(Yii::t("PcReportContentModule.general", "login"), array(Yii::app()->user->loginUrl[0]))
                    )
                )
            //'{login}' => $this->createUrl("/user/login")))
            );
        } else if (!Yii::app()->user->checkAccess('report inappropriate content')) {
            Yii::log(
                "User id=" . Yii::app()->user->id . ' attempted to report inappropriate content on model name=' . $_POST['model_name'] .
                ", model_id=" . $_POST['model_id'] . ' but he/she doesnt have permissions!',
                CLogger::LEVEL_WARNING,
                'SECURITY ' . __METHOD__
            );
            $this->_finishWithError(Yii::t('PcReportContentModule.general', 'Error occurred'));
        }

        // validate we have modelName, modelId
        set_error_handler(array($this, 'tempErrorHandler'), error_reporting());
        if ((!class_exists($_POST['model_name'])) || (empty($_POST['model_id']))) {
            // not enough data to process this request. might be a security breach attempt. report and abort
            Yii::log(
                "User id=" . Yii::app()->user->id . ' attempted to report inappropriate content on model name=' . $_POST['model_name'] .
                ", model_id=" . $_POST['model_id'] . ' but either class name could not be validated and/or model id is empty.',
                CLogger::LEVEL_WARNING,
                'SECURITY ' . __METHOD__
            );
            $this->_finishWithError(Yii::t('PcReportContentModule.general', 'Error occurred'));
        }
        // purge the temporary error handler set right above us
        restore_error_handler();

        // trim message if longer than it should
        if (mb_strlen($_POST['message']) > PcReportContentModule::MAX_REPORT_MSG_LENGTH) {
            $message = mb_substr($_POST['message'], 0, PcReportContentModule::MAX_REPORT_MSG_LENGTH) .
                "... (Message trimmed - exceeded max allowed length).";
        } else {
            $message = $_POST['message'];
        }

        // launch the method in the module class, check its return code, return accordingly to client side.
        if (!PcReportContentModule::reportContent(
            $_POST['model_name'],
            $_POST['model_id'],
            $message,
            Yii::app()->request->urlReferrer,
            Yii::app()->user->id,
            Yii::app()->user->name
        )
        ) {
            // report process failed. just notify client side
            $this->_finishWithError(Yii::t('PcReportContentModule.general', 'Error occurred'));
        }

        // all ok, and all done
        $this->_finishWithError(Yii::t('PcReportContentModule.general', 'Reported! Thank you'));
    }

    /**
     * Pretty prints the text message and finishes execution.
     * @param string $text
     */
    private function _finishWithError($text)
    {
        echo "$text";
        Yii::app()->end();
    }

    /**
     * This temporary error handler method is used to catch the use cases in which user submitted report includes a model name that
     * Yii does not recognize. In such a case a PHP Error (E_WARNING) is flagged and this will cause abortion of execution in 'Yii's way'.
     * What we want to to have better control over this and avoid the framework from interfering. So, we'll 'set_error_handler()' to this
     * method just
     *
     * @param $errno
     * @param $errstr
     */
public function tempErrorHandler($errno, $errstr)
{
    Yii::log(
        "User id=" . Yii::app()->user->id . ' attempted to report inappropriate content on model name=' . $_POST['model_name'] .
        ", model_id=" . $_POST['model_id'] . ' but either class name could not be validated and/or model id is empty. (A PHP Warning' .
        ' has been raised by Yii\'s autoloader).',
        CLogger::LEVEL_WARNING,
        'SECURITY ' . __METHOD__
    );
    $this->_finishWithError(Yii::t('PcReportContentModule.general', 'Error occurred'));
}
} 