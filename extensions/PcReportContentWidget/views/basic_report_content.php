<div class="row portlet portlet-decoration txtAlignLeft inappBox"
     id="report-content-<?php echo strtolower($model_class_name) . '-' . $model_id; ?>">
    <!--<h3 class="title">Inappropriate content?</h3>-->
    <?php
    echo CHtml::beginForm();
    echo CHtml::label(Yii::t("PcReportContentModule.general", "Reason"), 'message', array('class' => 'reasontitle'));
    echo "<div class='row'>";
    echo CHtml::hiddenField('model_name', $model_class_name);
    echo CHtml::hiddenField('model_id', $model_id);
    echo CHtml::textArea('message', Yii::t("PcReportContentModule.general", ""), array('cols' => 55, 'rows' => 5));
    echo "<div class='span1 c_author'><img src='" . Yii::app()->theme->getBaseUrl() . "/images/person.png'></div>";
    echo "</div><div class='row'><div class='span1 pull-right reportSendDV'>";
    echo CHtml::ajaxSubmitButton(
        Yii::t("PcReportContentModule.general", ""),
        array('/reportContent/ReportContentProcessor/processReport',),
        array('update' => "#report-content-" . strtolower($model_class_name) . '-' . $model_id),
        array("class" => "reportSend pull-right")
    );
    echo "</div><div class='span-1 pull-right smallTxtComment click_txt leftMargin0' id='reset_report'>Reset</div><div class='span-1 pull-right smallTxtComment click_txt leftMargin0' id='close_report'>Close</div></div>";
    echo CHtml::endForm();
    ?>
</div>
