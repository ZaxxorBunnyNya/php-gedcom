<?php

declare(strict_types=1);

namespace Gedcom;

use Gedcom\Parser\Interfaces\ParserInterface;

class Parser implements ParserInterface
{
    protected $_file;

    protected $_gedcom;

    protected $_errorLog = [];

    protected $_linesParsed = 0;

    protected $_line = '';

    protected $_lineRecord;

    protected $_linePieces = 0;

    protected $_returnedLine = '';

    protected $_lines = array();

    /**
     * Constructs a Parser instance.
     *
     * Initializes a new Parser object, optionally with a pre-existing Gedcom object.
     *
     * @param Gedcom|null $gedcom An optional Gedcom object to use.
     */
    public function __construct(Gedcom|null $gedcom = null)
    {
        $this->_gedcom = is_null($gedcom) ? new Gedcom() : $gedcom;
    }

    /**
     * Advances the parser to the next line in the GEDCOM file.
     *
     * If a line was previously returned by the back() method, it sets that as the current line. Otherwise,
     * it reads the next line from the file.
     *
     * @return $this The instance of the Parser for method chaining.
     */
    public function forward(): self
    {
        // if there was a returned line by back(), set that as our current
        // line and blank out the returnedLine variable, otherwise grab
        // the next line from the file

        if (!empty($this->_returnedLine)) {
            $this->_line = $this->_returnedLine;
            $this->_returnedLine = '';
        } else {
            $this->_line = $this->_lines[$this->_linesParsed];
            $this->_lineRecord = null;
            $this->_linesParsed++;
        }

        return $this;
    }

    /**
     * Moves the parser back to the previously read line.
     *
     * Stores the current line for the previous parser to analyze, effectively moving the parser's position back.
     *
     * @return $this The instance of the Parser for method chaining.
     */
    public function back(): self
    {
        // our parser object encountered a line it wasn't meant to parse
        // store this line for the previous parser to analyze

        $this->_returnedLine = $this->_line;

        return $this;
    }


    /**
     * Skips to the next level in the GEDCOM file that is less than or equal to the specified level.
     *
     * This method leaves the parser at the line above the specified level, such that calling forward() will
     * result in landing at the correct level.
     *
     * @param int $level The level to skip to.
     */
    public function skipToNextLevel($level)
    {
        $currentDepth = 999;

        while ($currentDepth > $level) {
            $this->forward();
            $record = $this->getCurrentLineRecord();
            $currentDepth = (int) $record[0];
        }

        $this->back();
    }

    /**
     * Retrieves the Gedcom object associated with this parser.
     *
     * @return Gedcom The Gedcom object being parsed.
     */
    public function getGedcom(): Gedcom|null
    {
        return $this->_gedcom;
    }

    /**
     * Checks if the end of the GEDCOM file has been reached.
     *
     * @return bool True if the end of the file has been reached, false otherwise.
     */
    public function eof(): bool
    {
        if($this->_file === null){
            return false;
        }

        return feof($this->_file);
    }

    /**
     * Parses a multi-line record from the GEDCOM file.
     *
     * This method handles records that span multiple lines, aggregating the data according to the GEDCOM standard.
     *
     * @return string The aggregated data from the multi-line record.
     */
    public function parseMultiLineRecord()
    {
        $record = $this->getCurrentLineRecord();

        $depth = (int) $record[0];
        $data = isset($record[2]) ? trim((string) $record[2]) : '';

        $this->forward();

        while (!$this->eof()) {
            $record = $this->getCurrentLineRecord();
            $recordType = strtoupper(trim((string) $record[1]));
            $currentDepth = (int) $record[0];

            if ($currentDepth <= $depth) {
                $this->back();
                break;
            }

            switch ($recordType) {
                case 'DATA':
                    $dataInstance = new \Gedcom\Record\Data();
                    $this->forward();

                    while (!$this->eof()) {
                        $record = $this->getCurrentLineRecord();
                        $recordTypeData = strtoupper(trim((string) $record[1]));
                        $dataDepth = (int) $record[0];

                        if ($dataDepth <= $currentDepth) {
                            $this->back();
                            break;
                        }

                        switch ($recordTypeData) {
                            case 'TEXT':
                                $textData = isset($record[2]) ? trim((string) $record[2]) : '';
                                $dataInstance->setText($textData);
                                break;
                            case 'CONT':
                                $contData = isset($record[2]) ? "\n" + trim((string) $record[2]) : "\n";
                                $dataInstance->setText($dataInstance->getText() + $contData);
                                break;
                            default:
                                $this->back();
                                break 2;
                        }

                        $this->forward();
                    }

                    // Logic to associate $dataInstance with its parent object goes here

                    break;
                case 'CONT':
                    $data .= "\n";

                    if (isset($record[2])) {
                        $data .= trim((string) $record[2]);
                    }
                    break;
                case 'CONC':
                    if (isset($record[2])) {
                        $data .= ' ' . trim((string) $record[2]);
                    }
                    break;
                default:
                    $this->back();
                    break 2;
            }

            $this->forward();
        }

        return $data;
    }

    /**
     * Retrieves the current line from the GEDCOM file.
     *
     * @return string The current line being parsed.
     */
    public function getCurrentLine()
    {
        return $this->_line;
    }

    /**
     * Splits the current line into record components.
     *
     * This method splits the current line based on the specified number of pieces, caching the result for efficiency.
     *
     * @param int $pieces The number of pieces to split the line into.
     * @return array|false The split line as an array of components, or false if the line is empty.
     */
    public function getCurrentLineRecord($pieces = 3)
    {
        if (!is_null($this->_lineRecord) && $this->_linePieces == $pieces) {
            return $this->_lineRecord;
        }

        if (empty($this->_line)) {
            return false;
        }

        $line = trim((string) $this->_line);

        $this->_lineRecord = explode(' ', $line, $pieces);
        $this->_linePieces = $pieces;

        return $this->_lineRecord;
    }

    /**
     * Logs an error encountered during parsing.
     *
     * This method adds an error message to the internal log for later retrieval.
     *
     * @param string $error The error message to log.
     */
    protected function logError($error)
    {
        $this->_errorLog[] = $error;
    }

    /**
     * Logs an unhandled record encountered during parsing.
     *
     * This method logs a record that could not be handled, along with optional additional information.
     *
     * @param string $additionalInfo Optional additional information about the unhandled record.
     */
    public function logUnhandledRecord($additionalInfo = '')
    {
        $this->logError(
            $this->_linesParsed . ': (Unhandled) ' . trim(implode('|', $this->getCurrentLineRecord())) .
                (empty($additionalInfo) ? '' : ' - ' . $additionalInfo)
        );
    }

    /**
     * Logs a record that was skipped during parsing.
     *
     * This method logs a record that was intentionally skipped, along with optional additional information.
     *
     * @param string $additionalInfo Optional additional information about the skipped record.
     */
    public function logSkippedRecord($additionalInfo = '')
    {
        $this->logError(
            $this->_linesParsed . ': (Skipping) ' . trim(implode('|', $this->getCurrentLineRecord())) .
                (empty($additionalInfo) ? '' : ' - ' . $additionalInfo)
        );
    }

    /**
     * Retrieves the list of errors logged during parsing.
     *
     * @return array An array of error messages logged during parsing.
     */
    public function getErrors()
    {
        return $this->_errorLog;
    }

    /**
     * Normalizes an identifier by trimming whitespace and '@' characters.
     *
     * @param string $identifier The identifier to normalize.
     * @return string The normalized identifier.
     */
    public function normalizeIdentifier($identifier)
    {
        $identifier = trim((string) $identifier);

        return trim($identifier, '@');
    }

    /**
     * getFormatTypeBySourceName
     *
     * @param  mixed $sourceName
     * @return Gedcom\Enums\SpeceficFormats
     */
    public function getFormatTypeBySourceName(string $sourceName): \Gedcom\Enums\SpeceficFormats
    {
        if($sourceName === "FamyTale"){
            return \Gedcom\Enums\SpeceficFormats::Famytale;
        }

        return \Gedcom\Enums\SpeceficFormats::Unkown;
    }


    /**
     * setFirstCard
     *
     * @return self
     */
    public function setFirstCard(): self
    {
        $gedcom = $this->getGedcom();
        $format = $gedcom->getSpecificFormat();

        if ($format === null || $format === \Gedcom\Enums\SpeceficFormats::Unkown) {
            return $this;
        }

        if ($format === \Gedcom\Enums\SpeceficFormats::Famytale) {
            $indi = $gedcom->getIndi();

            $gedcom->setMainIndi($indi[array_key_first($indi)]);
            $this->_gedcom = $gedcom;

            return $this;
        }

        return $this;
    }

    public function detectSpecificFileFormat(): self
    {
        $gedcom = $this->getGedcom();

        if ($gedcom === null) {
            error_log("Failed to detect format");

            return $this;
        }

        $source = $gedcom
            ->getHead()
            ->getSour()
            ->getName();

        if ($source === null) {
            $gedcom->setSpecificFormat(\Gedcom\Enums\SpeceficFormats::Unkown);

            return $this;
        }

        $gedcom->setSpecificFormat(
            $this->getFormatTypeBySourceName((string)$source)
        );

        $this->_gedcom = $gedcom;

        return $this;
    }

    /**
     * Parses a GEDCOM file.
     *
     * Opens and reads a GEDCOM file, parsing its contents and populating the Gedcom object.
     *
     * @param string $fileName The path to the GEDCOM file to parse.
     * @return Gedcom|null The Gedcom object populated with data from the file, or null on failure.
     */
    public function parse($fileName): ?Gedcom
    {
        $this->_file = fopen($fileName, 'r');

        if (!$this->_file) {
            error_log("Failed to open file: " . $fileName);

            return $this->getGedcom();
        }

        $fileData =  file_get_contents($fileName);

        return $this->parseString($fileData);
    }

    public function parseString(string $string): ?Gedcom
    {
        $this->_lines = $this->splitString($string);

        $this->forward();

        while (!$this->eof()) {
            $record = $this->getCurrentLineRecord();

            if ($record === false) {
                continue;
            }

            $depth = (int) $record[0];

            // We only process 0 level records here. Sub levels are processed
            // in methods for those data types (individuals, sources, etc)

            if ($depth == 0) {
                // Although not always an identifier (HEAD,TRLR):
                if (isset($record[1])) {
                    $this->normalizeIdentifier($record[1]);
                }

                if (isset($record[1]) && trim((string) $record[1]) == 'HEAD') {
                    \Gedcom\Parser\Head::parse($this);
                } elseif (isset($record[2]) && trim((string) $record[2]) == 'SUBN') {
                    \Gedcom\Parser\Subn::parse($this);
                } elseif (isset($record[2]) && trim((string) $record[2]) == 'SUBM') {
                    \Gedcom\Parser\Subm::parse($this);
                } elseif (isset($record[2]) && $record[2] == 'SOUR') {
                    \Gedcom\Parser\Sour::parse($this);
                } elseif (isset($record[2]) && $record[2] == 'INDI') {
                    \Gedcom\Parser\Indi::parse($this);
                } elseif (isset($record[2]) && $record[2] == 'FAM') {
                    \Gedcom\Parser\Fam::parse($this);
                } elseif (isset($record[2]) && str_starts_with(trim((string) $record[2]), 'NOTE')) {
                    \Gedcom\Parser\Note::parse($this);
                } elseif (isset($record[2]) && $record[2] == 'REPO') {
                    \Gedcom\Parser\Repo::parse($this);
                } elseif (isset($record[2]) && $record[2] == 'OBJE') {
                    \Gedcom\Parser\Obje::parse($this);
                } elseif (isset($record[1]) && trim((string) $record[1]) == 'TRLR') {
                    // EOF
                    break;
                } else {
                    $this->logUnhandledRecord(self::class . ' @ ' . __LINE__);
                }
            } else {
                $this->logUnhandledRecord(self::class . ' @ ' . __LINE__);
            }

            $this->forward();
        }

        $this->detectSpecificFileFormat()
            ->setFirstCard();

        return $this->getGedcom();
    }

    private function splitString(string $string): array
    {
        $newString = mb_convert_encoding($string, 'UTF-8');

        return explode(PHP_EOL, $newString);
    }
}
