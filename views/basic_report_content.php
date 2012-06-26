<div id="report-content-<?php echo strtolower($model_class_name) . '-' . $model_id;?>">
	<h3 class="title">Inappropriate content?</h3>
	<?php
	echo CHtml::beginForm();
	$reason_field_name = "rc-" . strtolower($model_class_name) . '-' . $model_id;
	echo CHtml::label(Yii::t("PcReportContent.general", "Reason"), $reason_field_name);
	echo CHtml::textArea($reason_field_name, Yii::t("PcReportContent.general", "Please describe..."));
	// add hidden field that contains the poll id
	echo CHtml::hiddenField($ajax_submit_parameter, true);
	// continue here...
	echo CHtml::ajaxSubmitButton(
		Yii::t("PcReportContent.general", "Submit Report"),
		array(
			'report.ProcessReportContent',
			'name' => $model_class_name,
			'id' => $model_id),
		array('update' => "#report-content-" . strtolower($model_class_name) . '-' . $model_id));
	echo CHtml::endForm();
	?>
</div>