<?php

namespace admin\models;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use Yii;
use yii\data\ActiveDataProvider;

class DeliveryExcel
{
    public static function build(ActiveDataProvider $provider)
    {
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getProtection()->setSheet(true);

        $reasonSheet = $spreadsheet->createSheet();
        $reasonSheet->setTitle('Translate');
        $reasonSheet->getProtection()->setSheet(true);

        $headerIndex = 1;
        $sheet->setCellValue('A' . $headerIndex, 'ID');
        $sheet->setCellValue('B' . $headerIndex, 'Русский текст');
        $sheet->setCellValue('C' . $headerIndex, 'Перевод');

        foreach ($provider->query->asArray()->all() as $i => $row) {
            $n = $i + 2;
            $sheet->setCellValue('A' . $n, $row['id']);
            $sheet->setCellValue('B' . $n, $row['text']);
            $sheet->setCellValue('C' . $n, $row['toText']);
            $spreadsheet->getActiveSheet()->getStyle('C' . $n)
                ->getProtection()
                ->setLocked(Protection::PROTECTION_UNPROTECTED);
        }

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="positions.xls"');
        header('Cache-Control: max-age=0');
        header('Access-Control-Allow-Origin: *');

        $writer = IOFactory::createWriter($spreadsheet, 'Xls');
        $writer->save('php://output');
        exit();
    }

    public static function import($instance)
    {
        $sourceFile = $instance;
        $inputFileType = IOFactory::identify($sourceFile->tempName);
        $reader = IOFactory::createReader($inputFileType);
        $reader->setReadDataOnly(TRUE);
        $spreadsheet = $reader->load($sourceFile->tempName);
        $worksheet = $spreadsheet->getActiveSheet();
        $transaction = Yii::$app->db->beginTransaction();
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            $id = '';
            $translate = '';
            foreach ($cellIterator as $cell) {
                if ($cell->getColumn() == 'A') {
                    $id = $cell->getValue();
                }
                if ($cell->getColumn() == 'C') {
                    $translate = $cell->getValue();
                }
                if ($id && $translate) {
                    $lang = LangTo::findOne((int)$id);
                    if ($lang) {
                        $lang->text = $translate;
                        $lang->save();
                    }
                    $id = '';
                    $translate = '';
                }
            }
        }
        $transaction->commit();
    }

}