<?php
/**
 * ProcessReportContent.php
 *
 */
class ProcessReportContent extends CAction {
	/**
	 * Processes 'report inappropriate content' submits
	 * should return message to client side noting success/failure
	 */
	public function run() {
		/* We'd like to have all functionality in the widget so we do a little trick here - instantiate the widget in
		 * 'captureOutput' mode and return from here its 'answer'.
		 */
		if ((Yii::app()->request->getParam(PcReportContent::REPORT_CONTENT_AJAX_NOTIFIER)) && (Yii::app()->request->isAjaxRequest)) {
			// instantiate the widget with $captureOutput = 1.
			// echo the content received... . The widget itself will do the rest! :)
			$results = Yii::app()->getController()->widget(
				'PcReportContent',
				array('modelClassName' => Yii::app()->request->getParam('name'), 'modelClassId' => Yii::app()->request->getParam('id')),
				true);
			echo $results;
		}
	}
}
