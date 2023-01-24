<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Excel class for drafting Microsoft Excel documents.
 *
 */

class Excel {
	/**
	 *
	 * Constructor takes base save path argument.
	 *
	 * Returns false if path is not found or can't be created.
	 *
	 * @param  string  $save_path  Base path of saved spreadsheet documents.
	 * @return bool
	 *
	 */
	function __construct(
		$save_path = ''
	) {
		if(!$save_path) {
			$save_path = $_SERVER['DOCUMENT_ROOT'].'/spreadsheets/';
		}

		$this->save_path = $save_path;

		if(!is_dir($this->save_path)) {
			try {
				mkdir($this->save_path);
			} catch (\Exception $e) {
				return false;
			}
		}
	}

	function __destruct() {}

	/**
	 *
	 * Read a spreadsheet by full filename path.
	 *
	 * Returns false if no file is found.
	 *
	 * @param  string  $filepath
	 * @return array   $object    Array of excel cell values by row/column.
	 *
	 */
	public function readSpreadsheet(
		$filepath, 
		$readonly = true,
		$extension = 'Xlsx'
	) {
		$reader = IOFactory::createReader($extension);
		$reader->setReadDataOnly($readonly);

		try {
			$loader = $reader->load($filepath);
		} catch (Exception $e) {
			return false;
		}

		$sheet = $loader->getActiveSheet();
		$object = array();

		foreach($sheet->getRowIterator() as $i => $row) {
			foreach($row->getCellIterator() as $j => $cell) {
				// elog($cell->getValue());
				$object[$i][] = $cell->getValue();
			}
		}

		return $object;
	}

	/**
	 *
	 * Save a spreadsheet.
	 *
	 * Appends proper save path datestamp.
	 * Requires array formatted like:
	 *
	 * array(
	 *   // Row 1
	 *   array(
	 *     // Columns
	 *     'row 1, column 1', 
	 *     'row 1, column 2'
	 *   ),
	 *
	 *   // Row 2
	 *   array(
	 *     // Columns
	 *     'row 2, column 1',
	 *     'row 2, column 2'
	 *   ),
	 * );
	 *
	 * @param  string  $filename  Filename of speadsheet. Appends date, title, and full path.
	 * @param  array   $object    Array sorteded by row/column.
	 * @return bool
	 *
	 */
	public function writeSpreadsheet($filename, $object) {
		$spreadsheet = new Spreadsheet();
		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->setIncludeCharts(true);
		$activesheet = $spreadsheet->setActiveSheetIndex(0);

		foreach($object as $i => $row) {
			foreach($row as $j => $cell) {
				$activesheet->setCellValue($this->intToLetter($j).($i + 1), $cell);
			}
		}

		$now = time();

		$activesheet->setTitle(
			date('Y-m-d H:i:s', $now).
			($title ? ' - '.$title : '')
		);

		$writer->save($this->save_path.date('Y-m-d_', $now).$filename);

		return true;
	}

	/**
	 *
	 * Integer to letter helper.
	 *
	 * Returns letter code based on column index.
	 *
	 * @param  string  $int
	 * @return string  $map  Letter code.
	 *
	 */
	private function intToLetter($int) {
		$map = array(
			'A','B','C','D','E','F','G','H','I','J','K','L',
			'M','N','O','P','Q','R','S','T','U','V','W','X',
			'Y','Z','AA','AB','AC','AD','AE','AF','AG','AH',
			'AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR',
			'AS','AT','AU','AV','AW','AX','AY','AZ'
		);

		if($int >= count($map)) {
			$int = count($map) - 1;
		}

		$letter = $map[$int];

		return $letter;
	}

	/*
	Read example:

	$object = $excel->readSpreadsheet($_SERVER['DOCUMENT_ROOT'].'/spreadsheets/simple.xlsx');
	elog($object);


	Write example:

	$excel->writeSpreadsheet('testwrite.xlsx',
		array(
			array( // row 1
				'Cell 1', // row 1, cell 1
				'Cell 2'  // row 1, cell 2
			),
			array( // row 2
				'Cell 3', // row 2, cell 1
				'Cell 4'  // row 2, cell 2
			)
		)
	);
	*/
}


?>