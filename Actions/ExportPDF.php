<?php
namespace axenox\PDFPrinter\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Data;
use axenox\PDFPrinter\Interfaces\Actions\iCreatePdf;
use exface\Core\Actions\ExportJSON;
use axenox\PDFPrinter\Actions\Traits\iCreatePdfTrait;

/**
 * Exports data as downloadable PDF.
 *
 *
 *
 *  ## Filename Placeholders
 *
 *
 *
 *  You can dynamically generate filenames based on aggregated data, by using placeholders in the property `filename`.
 *  For example `"filename":"[#=Now('yyyy-MM-dd')#]_[#~data:Materialkategorie:LIST_DISTINCT#]"` could be used to include both
 *  the current date and some information about the categories present in the export and result in a filename like `2024-09-10_Muffen`.
 *
 *  ### Supported placeholders:
 *
 *  - `[#=Formula()#]` Allows the use of formulas.
 *  - `[#~data:attribute_alias:AGGREGATOR#]` Aggregates the data column for the given alias by applying the specified aggregator. See below for
 * a list of supported aggregators.
 *
 *
 *
 *  ### Supported aggregators:
 *
 *  - `SUM` Sums up all values present in the column. Non-numeric values will either be read as numerics or as 0, if they cannot be converted.
 *  - `AVG` Calculates the arithmetic mean of all values present in the column. Non-numeric values will either be read as numerics or as 0, if they cannot be converted.
 *  - `MIN` Gets the lowest of all values present in the column. If only non-numeric values are present, their alphabetic rank is used. If the column is mixed,
 *  non-numeric values will be read as numerics or as 0, if they cannot be converted.
 *  - `MAX` Gets the highest of all values present in the column. If only non-numeric values are present, their alphabetic rank is used. If the column is mixed,
 *   non-numeric values will be read as numerics or as 0, if they cannot be converted.
 *  - `COUNT` Counts the total number of rows in the column.
 *  - `COUNT_DISTINCT` Counts the number of unique entries in the column, excluding empty rows.
 *  - `LIST` Lists all non-empty rows in the column, applying the following format: `Some value,anotherValue,yEt another VaLue` => `SomeValue_AnotherValue_YetAnotherValue`
 *  - `LIST_DISTINCT` Lists all unique, non-empty rows in the column, applying the following format: `Some value,anotherValue,yEt another VaLue` => `SomeValue_AnotherValue_YetAnotherValue`
 *
 *
 */
class ExportPDF extends ExportJSON implements iCreatePdf
{
    use iCreatePdfTrait;
    
    private $contentHtml = null;    
    
    public function getMimeType() : ?string
    {
        if ($this->mimeType === null && get_class($this) === ExportPDF::class) {
            return 'application/pdf';
        }
        return $this->mimeType;
    }
    
    /**
     *
     * @return string
     */
    protected function getFileExtension() : string
    {
        return 'pdf';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeHeader($exportedWidget, $exportedSheet)
     */
    protected function writeHeader(array $exportedColumns) : array
    {
        $contentHtml = $this->writeHtmlBegin($exportedColumns);
        $columnNames = parent::writeHeader($exportedColumns);
        $contentHtml .= <<<HTML
            <table style="border-collapse: collapse; border: 0.5pt solid black; width: 100%">
                <thead>
                    <tr>
HTML;
        foreach ($columnNames as $name) {
            $contentHtml .= "<th>{$name}</th>";
        }
        $contentHtml .= <<<HTML
        
                    </tr>
                </thead>
                <tbody>
HTML;
        $this->contentHtml = $contentHtml;
        return $columnNames;
    }
    
    /**
     * 
     * @param array $exportedColumns
     * @return string
     */
    protected function writeHtmlBegin(array $exportedColumns) : string
    {
        $count = 0;
        $exportedWidget = $exportedColumns[0]->getDataWidget();
        if ($exportedWidget instanceof Data) {
            foreach ($exportedWidget->getFilters() as $filter_widget) {
                if ($filter_widget->getValue()) {
                    $count++;
                }
            }
        }
        $date = date("m.d.Y");
        if ($this->contentHtml === null) {
            $this->contentHtml = <<<HTML
        
<head>
        <style>
            @page {
                margin: 100px 28px;
            }
            header {
                position: fixed;
                top: -60px;
                left: 0px;
                right: 0px;
                height: 50px;
            }

            footer {
                position: fixed; 
                bottom: -60px; 
                left: 0px; 
                right: 0px;
                height: 50px;
            }
		</style>
    </head>
    <body>
        <!-- Define header and footer blocks before your content -->
        <header>
        <div style="text-align: center;"><span style="color:gray; font-size:0.8em;">{$exportedWidget->getMetaObject()->getName()} ({$exportedWidget->getMetaObject()->getAliasWithNamespace()})</span></div>
        <hr style="height:2px; border-width:0; color:gray; background-color:gray">
        </header>

        <footer>
            <hr style="height:2px; border-width:0; color:gray; background-color:gray">
            <table style="width: 100%; font-size:0.8em;">
                <tbody>
                    <tr>
                        <td style="border: none; width: 25%"><span style="color:gray;">{$this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getName()}</span></td>
                        <td style="border: none; width: 25%"><span style="color:gray;">{$date}</span></td>
                        <td style="border: none; width: 25%"><span style="color:gray;">Filter: {$count}</span></td>
                        <td style="border: none; width: 25%; text-align: right;"><span class="page-number" style="color:gray;">Page </span></td>
                    </tr>
                </tbody>
            </table>
        </footer>
        <main>
            <style type="text/css">
                td {padding: 5px; border: 0.5pt solid black;}
                th {padding: 5px; border: 0.5pt solid black;}
                .page-number:after { content: counter(page); }
            </style>            
HTML;
        }
        return $this->contentHtml;
    }
    
    /**
     * Generates rows from the passed DataSheet and writes them as html table rows.
     *
     * The cells of the row are added in the order specified by the passed columnNames array.
     * Cells which are not specified in this array won't appear in the result output.
     *
     * @param DataSheetInterface $dataSheet
     * @param string[] $columnNames
     * @return string
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $columnNames)
    {
        $contentHtml = $this->contentHtml;
        $rowsHtml = "";
        foreach ($dataSheet->getRows() as $row) {
            $outRow = [];
            foreach ($columnNames as $key => $value) {
                $outRow[$key] = $row[$key];
            }
            $rowsHtml .= "<tr>";
            foreach ($outRow as $value) {
                $rowsHtml .= "<td>{$value}</td>";
            }
            $rowsHtml .= "</tr>";
        }
        $contentHtml .= $rowsHtml;
        $contentHtml .= <<<HTML
                </tbody>
            </table>
HTML;
        $this->contentHtml = $contentHtml;
        return;
    }
    
    /**
     * Writes the terminated file to the path from getFilePathAbsolute().
     *
     * @param DataSheetInterface $dataSheet
     * @return void
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {
        $contentHtml = $this->contentHtml;
        $contentHtml .= $this->buildFilterLegendHtml($dataSheet);
        $contentHtml .= <<<HTML
        </main>
    </body>
</html>
HTML;
        $filecontent = $this->createPdf($contentHtml, $this->getOrientation());
        $this->initializeFilePathAbsolute($dataSheet);
        fwrite($this->getWriter(), $filecontent);
        fclose($this->getWriter());
    }
    
    protected function buildFilterLegendHtml(DataSheetInterface $dataSheet) : string
    {
        $html = '';
        $filterData = $this->getFilterData($dataSheet);
        if (empty($filterData)) {
            return $html;
        }
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $html = <<<HTML
            <div style="page-break-before: always;">
                <h2>{$translator->translate('ACTION.EXPORTXLSX.FILTER')}</h2>
 
                <table style="border-collapse: collapse; border: 0.5pt solid black; width: 100%">                    
                    <tbody>
                    
HTML;
        foreach ($filterData as $key => $value) {
            $html .= <<<HTML
                        <tr>
                            <td style="width: 25%; padding: 5px; overflow:hidden;">{$key}</td>
                            <td style="width: 75%; padding: 5px; overflow:hidden;">{$value}</td>
                        </tr>
HTML;
        }
        $html .= <<<HTML
                    </tbody>
                </table>
            </div>
HTML;
        return $html;
    }
    
    /**
     * 
     * @return resource
     */
    protected function getWriter()
    {
        if (is_null($this->writer)) {
            $this->writer = fopen($this->getFilePathAbsolute(), 'x+');
        }
        return $this->writer;
    }
}