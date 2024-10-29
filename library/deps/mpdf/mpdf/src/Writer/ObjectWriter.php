<?php
/**
 * @license GPL-2.0-only
 *
 * Modified by Kodezen on 03-October-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Academy\Mpdf\Writer;

use Academy\Mpdf\Strict;
use Academy\Mpdf\Mpdf;
use pdf_parser;

final class ObjectWriter
{

	use Strict;

	/**
	 * @var \Academy\Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Academy\Mpdf\Writer\BaseWriter
	 */
	private $writer;

	public function __construct(Mpdf $mpdf, BaseWriter $writer)
	{
		$this->mpdf = $mpdf;
		$this->writer = $writer;
	}

	public function writeImportedObjects()
	{
		if (is_array($this->mpdf->parsers) && count($this->mpdf->parsers) > 0) {

			foreach ($this->mpdf->parsers as $filename => $p) {

				$this->mpdf->current_parser = $this->mpdf->parsers[$filename];

				if (is_array($this->mpdf->_obj_stack[$filename])) {

					while ($n = key($this->mpdf->_obj_stack[$filename])) {

						$nObj = $this->mpdf->current_parser->resolveObject($this->mpdf->_obj_stack[$filename][$n][1]);
						$this->writer->object($this->mpdf->_obj_stack[$filename][$n][0]);

						if ($nObj[0] == pdf_parser::TYPE_STREAM) {
							$this->mpdf->pdf_write_value($nObj);
						} else {
							$this->mpdf->pdf_write_value($nObj[1]);
						}

						$this->writer->write('endobj');

						$this->mpdf->_obj_stack[$filename][$n] = null; // free memory

						unset($this->mpdf->_obj_stack[$filename][$n]);

						reset($this->mpdf->_obj_stack[$filename]);
					}
				}
			}
		}
	}

}