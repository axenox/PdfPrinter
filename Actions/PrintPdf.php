<?php
namespace axenox\PDFPrinter\Actions;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use axenox\PDFPrinter\Interfaces\Actions\iCreatePdf;
use axenox\PDFPrinter\Actions\Traits\iCreatePdfTrait;
use exface\Core\Actions\PrintTemplate;
use exface\Core\DataTypes\StringDataType;

/**
 * This action produces PDFs from HTML-based templates.
 * 
 * The template can either be set inside the action property `template` or read from
 * a file specified by the `template_path` (absolute or relative to the vendor folder).
 * 
 * Under the hood, the `dompdf` library is used to convert HTML to PDF. There is a playground,
 * you can use to scetch up your PDFs here: https://eclecticgeek.com/dompdf/debug.php. This
 * link is also usefull if you receive unexpected results and need some debug information.
 * 
 * ## Template placeholders
 * 
 * The template must be valid HTML and can contain the following placehodlers:
 * 
 * - `[#~config:app_alias:config_key#]` - will be replaced by the value of the `config_key` in the given app
 * - `[#~translate:app_alias:translation_key#]` - will be replaced by the translation of the `translation_key` 
 * from the given app
 * - `[#~input:column_name#]` - will be replaced by the value from `column_name` of the input data sheet
 * of the action
 * - `[#=Formula()#]` - will evaluate the formula (e.g. `=Now()`) in the context each row of the input data
 * - `[#~file:name#]` and `[#~file:name_without_ext#]` - well be replaced by the name of the rendered file
 * with our without extension.
 * - additional custom placeholders can be defined in `data_placeholders` - see below.
 * 
 * ## Data placeholders
 * 
 * In addition to the general placeholders above, additional data can be loaded into the table:
 * e.g. positions of an order in addition to the actual order data, which is the input of the action.
 * 
 * Each entry in `data_placeholders` consists of a custom placeholder name (to be used in the main `template`) 
 * and a configuration for its contents:
 * 
 * 
 * - `data_sheet` to load the data - you can use the regular placeholders above here to define filters
 * - `row_template` to fill with placeholders from every row of the `data_sheet` - e.g. 
 * `[#dataPlaceholderName:some_attribute#]`, `[#dataPlaceholderName:=Formula()#]`.
 * - `row_template_if_empty` - a text to print when there is no data
 * - `outer_template` and `outer_template_if_empty` to wrap rows in a HTML table, border or
 * similar also for the two cases of having some data and not.
 * - nested `data_placeholders` to use inside each data placeholder
 * 
 * ## Example
 * 
 * Concider the following example for a simple order print template in HTML. Assume, that the `ORDER` 
 * object has its order number in the `ORDERNO` attribute and multiple related `ORDER_POSITION`
 * objects, that are to be printed as an HTML `<table>`. The below configuration creates a data
 * placeholder for the positions and defines a data sheet to load them. the `[#positions#]` placeholder
 * in the main `template` will be replaced by a concatennation of rendered `row_template`s. The
 * `data_sheet` used in the configuration of the data placeholder contains placeholders itself: in this
 * case, the `[#~input:ORDERNO#]`, with will be replace by the order number from the input data before
 * the sheet is read. The `row_template` now may contain global placeholders and those from it's
 * data placeholder rows - prefixed with the respective placeholder name.
 * 
 * ```
 *  {
 *      "filename": "Order [#~input:ORDERNO#].pdf",
 *      "template": "Order number: [#~input:ORDERNO#] <br><br>",
 *      "data_placeholders": {
 *          "positions": {
 *              "outer_template": "<table><tr><th>Product</th><th>Price</th></tr>[#positions#]</table>",
 *              "outer_template_if_empty": "<p>This order is empty</p>",
 *              "row_template": "<tr><td>[#~data:product#]</td><td>[#~data:price#]</td></tr>",
 *              "data_sheet": {
 *                  "object_alias": "my.App.ORDER_POSITION",
 *                  "columns": [
 *                      {"attribute_alias": "product"},
 *                      {"attribute_alias": "price"}
 *                  ],
 *                  "filters": {
 *                      "operator": "AND",
 *                      "conditions": [
 *                          {"expression": "ORDER__NO", "comparator": "==", "value": "[#~input:ORDERNO#]"}
 *                      ]
 *                  }
 *              }
 *          }
 *      }
 *  }
 * 
 * ```
 * 
 * @author Ralf Mulansky
 *
 */
class PrintPdf extends PrintTemplate implements iCreatePdf
{
    use iCreatePdfTrait;
    
    private $createAsHtml = false;
    
    /**
     * {@inheritdoc}
     * @see PrintTemplate::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputData = $this->getInputDataSheet($task);
        $contents = $this->renderTemplate($inputData);
        
        foreach ($contents as $filePath => $html) {
            if ($this->createAsHtml === true) {
                if (StringDataType::endsWith($filePath, '.pdf', false)) {
                    $filePath = (StringDataType::substringBefore($filePath, '.pdf') . '.html');
                }
                file_put_contents($filePath, $html);
            } else {
                file_put_contents($filePath, $this->createPdf($html));
            }
        }
        if ($filePath) {
            $result = ResultFactory::createFileResultFromPath($task, $filePath, $this->isDownloadable());
        } else {
            $result = ResultFactory::createEmptyResult($task);
        }
        
        return $result;
    }
    
    /**
     * {@inheritdoc}
     * @see PrintTemplate::getFileExtensionDefault()
     */
    protected function getFileExtensionDefault() : string
    {
        return '.pdf';
    }
    
    /**
     * @uxon-property create_as_html
     * @uxon-type boolean
     * 
     * @param bool $trueOrFalse
     */    
    public function setCreateAsHtml(bool $trueOrFalse)
    {
        $this->createAsHtml = $trueOrFalse;
        $this->setMimeType('text/html');
    }
    
    /**
     * {@inheritdoc}
     * @see PrintTemplate::getMimeType()
     */
    public function getMimeType() : ?string
    {
        return parent::getMimeType() ?? 'application/pdf';
    }
}