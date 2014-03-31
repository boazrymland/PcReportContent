<?php

/**
 * Report content widget class
 */
class PcReportContentWidget extends CWidget
{
    /* @var string $modelClassName holds the class name 'this' instance of widget refers to and should report on. */
    public $modelClassName;

    /* @var string $modelClassId holds the model id 'this' instance of widget refers to and should report on. Default = 'MID' for
     *      'template form' rendering, used where mass rendering of this widget (such as for the 'comments' use case) is avoided.
     */
    public $modelClassId = "MID";

    /**
     * Init method.
     *
     * @throw CException on unrecoverable problems.
     */
    public function init()
    {
        // validate the variables we need are set.
        if (!isset($this->modelClassName)) {
            throw new CException("Cannot work with an unknown model name. Please set modelClassName when calling this widget.");
        }
    }

    /**
     * run method
     *
     * @return mixed
     */
    public function run()
    {
        // render the widget
        $this->render(
            'basic_report_content',
            array('model_class_name' => $this->modelClassName, 'model_id' => $this->modelClassId,)
        );
    }
}
