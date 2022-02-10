<?php
namespace axenox\PDFPrinter\Actions;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use axenox\PDFPrinter\Interfaces\Actions\iCreatePdf;
use axenox\PDFPrinter\Actions\Traits\iCreatePdfTrait;
use exface\Core\Actions\PrintTemplate;

/**
 * This action produces PDFs from HTML-based templates.
 * 
 * The template can either be set inside the action property `template` or read from
 * a file specified by the `template_path` (absolute or relative to the vendor folder).
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
 * - `[#~input:=Formula()#]` - will evaluate the formula (e.g. `=Now()`) in the context each row of the input data
 * - any other placeholders defined in `data_placeholders` - see below.
 * 
 * ## Data placeholders
 * 
 * In addition to the general placeholders above, additional data can be loaded into the table:
 * e.g. positions of an order in addition to the actual order data, which is the input of the action.
 * 
 * Each entry in `data_placeholders` consists of a custom placeholder name (to be used in the main `template`) 
 * and a configuration for its contents:
 * 
 * - `data_sheet` to load the data 
 * - `row_template` to fill with placeholders from every row of the `data_sheet` - e.g. `[#~data:some_attribute#]`, `[#~data:=Formula()#]`.
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
 * data placeholder rows - prefixed with `~data:`.
 * 
 * ```
 * {
 *      "template": "Order number: [#~input:ORDERNO#] <br><br> <table><tr><th>Product</th><th>Price</th></tr>[#positions#]</table>",
 *      "filename": "Order [#~input:ORDERNO#].pdf",
 *      "data_placeholders": {
 *          "positions": {
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
 * }
 * 
 * ```
 * 
 * @author Ralf Mulansky
 *
 */
class PrintPdf extends PrintTemplate implements iCreatePdf
{
    use iCreatePdfTrait;
    
    /**
     * {@inheritdoc}
     * @see PrintTemplate::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputData = $this->getInputDataSheet($task);
        $contents = $this->renderTemplate($inputData);
        
        foreach ($contents as $filePath => $html) {
            file_put_contents($filePath, $this->createPdf($html));
        }
        $result = ResultFactory::createFileResult($task, $filePath);
        
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
     * {@inheritdoc}
     * @see PrintTemplate::getMimeType()
     */
    public function getMimeType() : ?string
    {
        return parent::getMimeType() ?? 'application/pdf';
    }
}