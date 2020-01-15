<?php
//  ------------------------------------------------------------------------ //
//                      USERPAGE - MODULE FOR XOOPS 2                        //
//                  Copyright (c) 2005-2006 Instant Zero                     //
//                     <http://xoops.instant-zero.com>                      //
// ------------------------------------------------------------------------- //
//  This program is free software; you can redistribute it and/or modify     //
//  it under the terms of the GNU General Public License as published by     //
//  the Free Software Foundation; either version 2 of the License, or        //
//  (at your option) any later version.                                      //
//                                                                           //
//  You may not change or alter any portion of this comment or credits       //
//  of supporting developers from this source code or any supporting         //
//  source code which is considered copyrighted (c) material of the          //
//  original comment or credit authors.                                      //
//                                                                           //
//  This program is distributed in the hope that it will be useful,          //
//  but WITHOUT ANY WARRANTY; without even the implied warranty of           //
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            //
//  GNU General Public License for more details.                             //
//                                                                           //
//  You should have received a copy of the GNU General Public License        //
//  along with this program; if not, write to the Free Software              //
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA //
//  ------------------------------------------------------------------------ //

defined('XOOPS_ROOT_PATH') || die('XOOPS root path not defined');

/**
 * @param     $lpszFileName
 * @param int $iIndex
 * @return bool|\CGIF
 */
function gif_loadFile($lpszFileName, $iIndex = 0)
{
    $gif = new CGIF();

    if (!$gif->loadFile($lpszFileName, $iIndex)) {
        return false;
    }

    return $gif;
}

///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * @param     $gif
 * @param     $lpszFileName
 * @param int $bgColor
 * @return bool
 */
function gif_outputAsBmp($gif, $lpszFileName, $bgColor = -1)
{
    if (!isset($gif) || ('cgif' !== @get_class($gif)) || !$gif->loaded() || ('' == $lpszFileName)) {
        return false;
    }

    $fd = $gif->getBmp($bgColor);
    if (mb_strlen($fd) <= 0) {
        return false;
    }

    if (!($fh = @fopen($lpszFileName, 'wb'))) {
        return false;
    }
    @fwrite($fh, $fd, mb_strlen($fd));
    @fflush($fh);
    @fclose($fh);

    return true;
}

///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * @param     $gif
 * @param     $lpszFileName
 * @param int $bgColor
 * @return bool
 */
function gif_outputAsPng($gif, $lpszFileName, $bgColor = -1)
{
    if (!isset($gif) || ('cgif' !== @get_class($gif)) || !$gif->loaded() || ('' == $lpszFileName)) {
        return false;
    }

    $fd = $gif->getPng($bgColor);
    if (mb_strlen($fd) <= 0) {
        return false;
    }

    if (!($fh = @fopen($lpszFileName, 'wb'))) {
        return false;
    }
    @fwrite($fh, $fd, mb_strlen($fd));
    @fflush($fh);
    @fclose($fh);

    return true;
}

///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * @param     $gif
 * @param     $lpszFileName
 * @param int $bgColor
 * @return bool
 */
function gif_outputAsJpeg($gif, $lpszFileName, $bgColor = -1)
{
    if (gif_outputAsBmp($gif, "$lpszFileName.bmp", $gbColor)) {
        exec("cjpeg $lpszFileName.bmp >$lpszFileName 2>/dev/null");
        @unlink("$lpszFileName.bmp");

        if (@file_exists($lpszFileName)) {
            if (@filesize($lpszFileName) > 0) {
                return true;
            }

            @unlink($lpszFileName);
        }
    }

    return false;
}

///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * @param $gif
 * @param $width
 * @param $height
 * @return bool
 */
function gif_getSize($gif, &$width, &$height)
{
    if (isset($gif) && ('cgif' === @get_class($gif)) && $gif->loaded()) {
        $width  = $gif->width();
        $height = $gif->height();
    } elseif (@file_exists($gif)) {
        $myGIF = new CGIF();
        if (!$myGIF->getSize($gif, $width, $height)) {
            return false;
        }
    } else {
        return false;
    }

    return true;
}

///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Class CGIFLZW
 */
class CGIFLZW
{
    public $MAX_LZW_BITS;
    public $Fresh;
    public $CodeSize;
    public $SetCodeSize;
    public $MaxCode;
    public $MaxCodeSize;
    public $FirstCode;
    public $OldCode;
    public $ClearCode;
    public $EndCode;
    public $Next;
    public $Vals;
    public $Stack;
    public $sp;
    public $Buf;
    public $CurBit;
    public $LastBit;
    public $Done;
    public $LastByte;

    ///////////////////////////////////////////////////////////////////////////

    // CONSTRUCTOR
    public function __construct()
    {
        $this->MAX_LZW_BITS = 12;
        unset($this->Next);
        unset($this->Vals);
        unset($this->Stack);
        unset($this->Buf);

        $this->Next  = range(0, (1 << $this->MAX_LZW_BITS) - 1);
        $this->Vals  = range(0, (1 << $this->MAX_LZW_BITS) - 1);
        $this->Stack = range(0, (1 << ($this->MAX_LZW_BITS + 1)) - 1);
        $this->Buf   = range(0, 279);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $data
     * @param $datLen
     * @return bool|string
     */
    public function deCompress($data, &$datLen)
    {
        $stLen  = mb_strlen($data);
        $datLen = 0;
        $ret    = '';

        // INITIALIZATION
        $this->LZWCommand($data, true);

        while (($iIndex = $this->LZWCommand($data, false)) >= 0) {
            $ret .= chr($iIndex);
        }

        $datLen = $stLen - mb_strlen($data);

        if (-2 != $iIndex) {
            return false;
        }

        return $ret;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $data
     * @param $bInit
     * @return int
     */
    public function LZWCommand(&$data, $bInit)
    {
        if ($bInit) {
            $this->SetCodeSize = ord($data[0]);
            $data              = mb_substr($data, 1);

            $this->CodeSize    = $this->SetCodeSize + 1;
            $this->ClearCode   = 1 << $this->SetCodeSize;
            $this->EndCode     = $this->ClearCode + 1;
            $this->MaxCode     = $this->ClearCode + 2;
            $this->MaxCodeSize = $this->ClearCode << 1;

            $this->GetCode($data, $bInit);

            $this->Fresh = 1;
            for ($i = 0; $i < $this->ClearCode; ++$i) {
                $this->Next[$i] = 0;
                $this->Vals[$i] = $i;
            }

            for (; $i < (1 << $this->MAX_LZW_BITS); ++$i) {
                $this->Next[$i] = 0;
                $this->Vals[$i] = 0;
            }

            $this->sp = 0;

            return 1;
        }

        if ($this->Fresh) {
            $this->Fresh = 0;
            do {
                $this->FirstCode = $this->GetCode($data, $bInit);
                $this->OldCode   = $this->FirstCode;
            } while ($this->FirstCode == $this->ClearCode);

            return $this->FirstCode;
        }

        if ($this->sp > 0) {
            $this->sp--;

            return $this->Stack[$this->sp];
        }

        while (($Code = $this->GetCode($data, $bInit)) >= 0) {
            if ($Code == $this->ClearCode) {
                for ($i = 0; $i < $this->ClearCode; ++$i) {
                    $this->Next[$i] = 0;
                    $this->Vals[$i] = $i;
                }

                for (; $i < (1 << $this->MAX_LZW_BITS); ++$i) {
                    $this->Next[$i] = 0;
                    $this->Vals[$i] = 0;
                }

                $this->CodeSize    = $this->SetCodeSize + 1;
                $this->MaxCodeSize = $this->ClearCode << 1;
                $this->MaxCode     = $this->ClearCode + 2;
                $this->sp          = 0;
                $this->FirstCode   = $this->GetCode($data, $bInit);
                $this->OldCode     = $this->FirstCode;

                return $this->FirstCode;
            }

            if ($Code == $this->EndCode) {
                return -2;
            }

            $InCode = $Code;
            if ($Code >= $this->MaxCode) {
                $this->Stack[$this->sp] = $this->FirstCode;
                $this->sp++;
                $Code = $this->OldCode;
            }

            while ($Code >= $this->ClearCode) {
                $this->Stack[$this->sp] = $this->Vals[$Code];
                $this->sp++;

                if ($Code == $this->Next[$Code]) {// Circular table entry, big GIF Error!
                    return -1;
                }

                $Code = $this->Next[$Code];
            }

            $this->FirstCode        = $this->Vals[$Code];
            $this->Stack[$this->sp] = $this->FirstCode;
            $this->sp++;

            if (($Code = $this->MaxCode) < (1 << $this->MAX_LZW_BITS)) {
                $this->Next[$Code] = $this->OldCode;
                $this->Vals[$Code] = $this->FirstCode;
                $this->MaxCode++;

                if (($this->MaxCode >= $this->MaxCodeSize) && ($this->MaxCodeSize < (1 << $this->MAX_LZW_BITS))) {
                    $this->MaxCodeSize *= 2;
                    $this->CodeSize++;
                }
            }

            $this->OldCode = $InCode;
            if ($this->sp > 0) {
                $this->sp--;

                return $this->Stack[$this->sp];
            }
        }

        return $Code;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $data
     * @param $bInit
     * @return int
     */
    public function GetCode(&$data, $bInit)
    {
        if ($bInit) {
            $this->CurBit   = 0;
            $this->LastBit  = 0;
            $this->Done     = 0;
            $this->LastByte = 2;

            return 1;
        }

        if (($this->CurBit + $this->CodeSize) >= $this->LastBit) {
            if ($this->Done) {
                if ($this->CurBit >= $this->LastBit) {
                    // Ran off the end of my bits
                    return 0;
                }

                return -1;
            }

            $this->Buf[0] = $this->Buf[$this->LastByte - 2];
            $this->Buf[1] = $this->Buf[$this->LastByte - 1];

            $Count = ord($data[0]);
            $data  = mb_substr($data, 1);

            if ($Count) {
                for ($i = 0; $i < $Count; ++$i) {
                    $this->Buf[2 + $i] = ord($data[$i]);
                }
                $data = mb_substr($data, $Count);
            } else {
                $this->Done = 1;
            }

            $this->LastByte = 2 + $Count;
            $this->CurBit   = ($this->CurBit - $this->LastBit) + 16;
            $this->LastBit  = (2 + $Count) << 3;
        }

        $iRet = 0;
        for ($i = $this->CurBit, $j = 0; $j < $this->CodeSize; ++$i, ++$j) {
            $iRet |= (0 != ($this->Buf[(int)($i / 8)] & (1 << ($i % 8)))) << $j;
        }

        $this->CurBit += $this->CodeSize;

        return $iRet;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Class CGIFCOLORTABLE
 */
class CGIFCOLORTABLE
{
    public $m_nColors;
    public $m_arColors;

    ///////////////////////////////////////////////////////////////////////////

    // CONSTRUCTOR
    public function __construct()
    {
        unset($this->m_nColors);
        unset($this->m_arColors);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $lpData
     * @param $num
     * @return bool
     */
    public function load($lpData, $num)
    {
        $this->m_nColors  = 0;
        $this->m_arColors = [];

        for ($i = 0; $i < $num; ++$i) {
            $rgb = mb_substr($lpData, $i * 3, 3);
            if (mb_strlen($rgb) < 3) {
                return false;
            }

            $this->m_arColors[] = (ord($rgb[2]) << 16) + (ord($rgb[1]) << 8) + ord($rgb[0]);
            $this->m_nColors++;
        }

        return true;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public function toString()
    {
        $ret = '';

        for ($i = 0; $i < $this->m_nColors; ++$i) {
            $ret .= chr($this->m_arColors[$i] & 0x000000FF) . // R
                    chr(($this->m_arColors[$i] & 0x0000FF00) >> 8) . // G
                    chr(($this->m_arColors[$i] & 0x00FF0000) >> 16);  // B
        }

        return $ret;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public function toRGBQuad()
    {
        $ret = '';

        for ($i = 0; $i < $this->m_nColors; ++$i) {
            $ret .= chr(($this->m_arColors[$i] & 0x00FF0000) >> 16) . // B
                    chr(($this->m_arColors[$i] & 0x0000FF00) >> 8) . // G
                    chr($this->m_arColors[$i] & 0x000000FF) . // R
                    "\x00";
        }

        return $ret;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $rgb
     * @return int
     */
    public function colorIndex($rgb)
    {
        $rgb = (int)$rgb & 0xFFFFFF;
        $r1  = ($rgb & 0x0000FF);
        $g1  = ($rgb & 0x00FF00) >> 8;
        $b1  = ($rgb & 0xFF0000) >> 16;
        $idx = -1;

        for ($i = 0; $i < $this->m_nColors; ++$i) {
            $r2 = ($this->m_arColors[$i] & 0x000000FF);
            $g2 = ($this->m_arColors[$i] & 0x0000FF00) >> 8;
            $b2 = ($this->m_arColors[$i] & 0x00FF0000) >> 16;
            $d  = abs($r2 - $r1) + abs($g2 - $g1) + abs($b2 - $b1);

            if ((-1 == $idx) || ($d < $dif)) {
                $idx = $i;
                $dif = $d;
            }
        }

        return $idx;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Class CGIFFILEHEADER
 */
class CGIFFILEHEADER
{
    public $m_lpVer;
    public $m_nWidth;
    public $m_nHeight;
    public $m_bGlobalClr;
    public $m_nColorRes;
    public $m_bSorted;
    public $m_nTableSize;
    public $m_nBgColor;
    public $m_nPixelRatio;
    public $m_colorTable;

    ///////////////////////////////////////////////////////////////////////////

    // CONSTRUCTOR
    public function __construct()
    {
        unset($this->m_lpVer);
        unset($this->m_nWidth);
        unset($this->m_nHeight);
        unset($this->m_bGlobalClr);
        unset($this->m_nColorRes);
        unset($this->m_bSorted);
        unset($this->m_nTableSize);
        unset($this->m_nBgColor);
        unset($this->m_nPixelRatio);
        unset($this->m_colorTable);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $lpData
     * @param $hdrLen
     * @return bool
     */
    public function load($lpData, &$hdrLen)
    {
        $hdrLen = 0;

        $this->m_lpVer = mb_substr($lpData, 0, 6);
        if (('GIF87a' !== $this->m_lpVer) && ('GIF89a' !== $this->m_lpVer)) {
            return false;
        }

        $this->m_nWidth  = $this->w2i(mb_substr($lpData, 6, 2));
        $this->m_nHeight = $this->w2i(mb_substr($lpData, 8, 2));
        if (!$this->m_nWidth || !$this->m_nHeight) {
            return false;
        }

        $b                   = ord(mb_substr($lpData, 10, 1));
        $this->m_bGlobalClr  = ($b & 0x80) ? true : false;
        $this->m_nColorRes   = ($b & 0x70) >> 4;
        $this->m_bSorted     = ($b & 0x08) ? true : false;
        $this->m_nTableSize  = 2 << ($b & 0x07);
        $this->m_nBgColor    = ord(mb_substr($lpData, 11, 1));
        $this->m_nPixelRatio = ord(mb_substr($lpData, 12, 1));
        $hdrLen              = 13;

        if ($this->m_bGlobalClr) {
            $this->m_colorTable = new CGIFCOLORTABLE();
            if (!$this->m_colorTable->load(mb_substr($lpData, $hdrLen), $this->m_nTableSize)) {
                return false;
            }
            $hdrLen += 3 * $this->m_nTableSize;
        }

        return true;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $str
     * @return int
     */
    public function w2i($str)
    {
        return ord(mb_substr($str, 0, 1)) + (ord(mb_substr($str, 1, 1)) << 8);
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Class CGIFIMAGEHEADER
 */
class CGIFIMAGEHEADER
{
    public $m_nLeft;
    public $m_nTop;
    public $m_nWidth;
    public $m_nHeight;
    public $m_bLocalClr;
    public $m_bInterlace;
    public $m_bSorted;
    public $m_nTableSize;
    public $m_colorTable;

    ///////////////////////////////////////////////////////////////////////////

    // CONSTRUCTOR
    public function __construct()
    {
        unset($this->m_nLeft);
        unset($this->m_nTop);
        unset($this->m_nWidth);
        unset($this->m_nHeight);
        unset($this->m_bLocalClr);
        unset($this->m_bInterlace);
        unset($this->m_bSorted);
        unset($this->m_nTableSize);
        unset($this->m_colorTable);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $lpData
     * @param $hdrLen
     * @return bool
     */
    public function load($lpData, &$hdrLen)
    {
        $hdrLen = 0;

        $this->m_nLeft   = $this->w2i(mb_substr($lpData, 0, 2));
        $this->m_nTop    = $this->w2i(mb_substr($lpData, 2, 2));
        $this->m_nWidth  = $this->w2i(mb_substr($lpData, 4, 2));
        $this->m_nHeight = $this->w2i(mb_substr($lpData, 6, 2));

        if (!$this->m_nWidth || !$this->m_nHeight) {
            return false;
        }

        $b                  = ord($lpData[8]);
        $this->m_bLocalClr  = ($b & 0x80) ? true : false;
        $this->m_bInterlace = ($b & 0x40) ? true : false;
        $this->m_bSorted    = ($b & 0x20) ? true : false;
        $this->m_nTableSize = 2 << ($b & 0x07);
        $hdrLen             = 9;

        if ($this->m_bLocalClr) {
            $this->m_colorTable = new CGIFCOLORTABLE();
            if (!$this->m_colorTable->load(mb_substr($lpData, $hdrLen), $this->m_nTableSize)) {
                return false;
            }
            $hdrLen += 3 * $this->m_nTableSize;
        }

        return true;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $str
     * @return int
     */
    public function w2i($str)
    {
        return ord(mb_substr($str, 0, 1)) + (ord(mb_substr($str, 1, 1)) << 8);
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Class CGIFIMAGE
 */
class CGIFIMAGE
{
    public $m_disp;
    public $m_bUser;
    public $m_bTrans;
    public $m_nDelay;
    public $m_nTrans;
    public $m_lpComm;
    public $m_gih;
    public $m_data;
    public $m_lzw;

    ///////////////////////////////////////////////////////////////////////////

    public function __construct()
    {
        unset($this->m_disp);
        unset($this->m_bUser);
        unset($this->m_bTrans);
        unset($this->m_nDelay);
        unset($this->m_nTrans);
        unset($this->m_lpComm);
        unset($this->m_data);
        $this->m_gih = new CGIFIMAGEHEADER();
        $this->m_lzw = new CGIFLZW();
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $data
     * @param $datLen
     * @return bool
     */
    public function load($data, &$datLen)
    {
        $datLen = 0;

        while (true) {
            $b    = ord($data[0]);
            $data = mb_substr($data, 1);
            ++$datLen;

            switch ($b) {
                case 0x21: // Extension
                    if (!$this->skipExt($data, $len = 0)) {
                        return false;
                    }
                    $datLen += $len;
                    break;
                case 0x2C: // Image
                    // LOAD HEADER & COLOR TABLE
                    if (!$this->m_gih->load($data, $len = 0)) {
                        return false;
                    }
                    $data   = mb_substr($data, $len);
                    $datLen += $len;

                    // ALLOC BUFFER
                    if (!($this->m_data = $this->m_lzw->deCompress($data, $len = 0))) {
                        return false;
                    }
                    $data   = mb_substr($data, $len);
                    $datLen += $len;

                    if ($this->m_gih->m_bInterlace) {
                        $this->deInterlace();
                    }

                    return true;
                case 0x3B: // EOF
                default:
                    return false;
            }
        }

        return false;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $data
     * @param $extLen
     * @return bool
     */
    public function skipExt(&$data, &$extLen)
    {
        $extLen = 0;

        $b    = ord($data[0]);
        $data = mb_substr($data, 1);
        ++$extLen;

        switch ($b) {
            case 0xF9: // Graphic Control
                $b              = ord($data[1]);
                $this->m_disp   = ($b & 0x1C) >> 2;
                $this->m_bUser  = ($b & 0x02) ? true : false;
                $this->m_bTrans = ($b & 0x01) ? true : false;
                $this->m_nDelay = $this->w2i(mb_substr($data, 2, 2));
                $this->m_nTrans = ord($data[4]);
                break;
            case 0xFE: // Comment
                $this->m_lpComm = mb_substr($data, 1, ord($data[0]));
                break;
            case 0x01: // Plain text
                break;
            case 0xFF: // Application
                break;
        }

        // SKIP DEFAULT AS DEFS MAY CHANGE
        $b    = ord($data[0]);
        $data = mb_substr($data, 1);
        ++$extLen;
        while ($b > 0) {
            $data   = mb_substr($data, $b);
            $extLen += $b;
            $b      = ord($data[0]);
            $data   = mb_substr($data, 1);
            ++$extLen;
        }

        return true;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $str
     * @return int
     */
    public function w2i($str)
    {
        return ord(mb_substr($str, 0, 1)) + (ord(mb_substr($str, 1, 1)) << 8);
    }

    ///////////////////////////////////////////////////////////////////////////

    public function deInterlace()
    {
        $data = $this->m_data;

        for ($i = 0; $i < 4; ++$i) {
            switch ($i) {
                case 0:
                    $s = 8;
                    $y = 0;
                    break;
                case 1:
                    $s = 8;
                    $y = 4;
                    break;
                case 2:
                    $s = 4;
                    $y = 2;
                    break;
                case 3:
                    $s = 2;
                    $y = 1;
                    break;
            }

            for (; $y < $this->m_gih->m_nHeight; $y += $s) {
                $lne          = mb_substr($this->m_data, 0, $this->m_gih->m_nWidth);
                $this->m_data = mb_substr($this->m_data, $this->m_gih->m_nWidth);

                $data = mb_substr($data, 0, $y * $this->m_gih->m_nWidth) . $lne . mb_substr($data, ($y + 1) * $this->m_gih->m_nWidth);
            }
        }

        $this->m_data = $data;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Class CGIF
 */
class CGIF
{
    public $m_gfh;
    public $m_lpData;
    public $m_img;
    public $m_bLoaded;

    ///////////////////////////////////////////////////////////////////////////

    // CONSTRUCTOR
    public function __construct()
    {
        $this->m_gfh     = new CGIFFILEHEADER();
        $this->m_img     = new CGIFIMAGE();
        $this->m_lpData  = '';
        $this->m_bLoaded = false;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $lpszFileName
     * @param $iIndex
     * @return bool
     */
    public function loadFile($lpszFileName, $iIndex)
    {
        if ($iIndex < 0) {
            return false;
        }

        // READ FILE
        if (!($fh = @fopen($lpszFileName, 'rb'))) {
            //  return false;
        }
        //for local Gif's
        //      $this->m_lpData = @fRead($fh, @fileSize($lpszFileName));
        //for remote Gif's
        while (!feof($fh)) {
            $this->m_lpData = $this->m_lpData . fread($fh, 1024);
            //$this->m_lpData = @fRead($fh, @fileSize($lpszFileName));
        }
        fclose($fh);
        // GET FILE HEADER
        if (!$this->m_gfh->load($this->m_lpData, $len = 0)) {
            return false;
        }
        $this->m_lpData = mb_substr($this->m_lpData, $len);

        do {
            if (!$this->m_img->load($this->m_lpData, $imgLen = 0)) {
                return false;
            }
            $this->m_lpData = mb_substr($this->m_lpData, $imgLen);
        } while ($iIndex-- > 0);

        $this->m_bLoaded = true;

        return true;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $lpszFileName
     * @param $width
     * @param $height
     * @return bool
     */
    public function getSize($lpszFileName, &$width, &$height)
    {
        if (!($fh = @fopen($lpszFileName, 'rb'))) {
            return false;
        }
        $data = @fread($fh, @filesize($lpszFileName));
        @fclose($fh);

        $gfh = new CGIFFILEHEADER();
        if (!$gfh->load($data, $len = 0)) {
            return false;
        }

        $width  = $gfh->m_nWidth;
        $height = $gfh->m_nHeight;

        return true;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $bgColor
     * @return bool|string
     */
    public function getBmp($bgColor)
    {
        $out = '';

        if (!$this->m_bLoaded) {
            return false;
        }

        // PREPARE COLOR TABLE (RGBQUADs)
        if ($this->m_img->m_gih->m_bLocalClr) {
            $nColors = $this->m_img->m_gih->m_nTableSize;
            $rgbq    = $this->m_img->m_gih->m_colorTable->toRGBQuad();
            if (-1 != $bgColor) {
                $bgColor = $this->m_img->m_gih->m_colorTable->colorIndex($bgColor);
            }
        } elseif ($this->m_gfh->m_bGlobalClr) {
            $nColors = $this->m_gfh->m_nTableSize;
            $rgbq    = $this->m_gfh->m_colorTable->toRGBQuad();
            if (-1 != $bgColor) {
                $bgColor = $this->m_gfh->m_colorTable->colorIndex($bgColor);
            }
        } else {
            $nColors = 0;
            $bgColor = -1;
        }

        // PREPARE BITMAP BITS
        $data = $this->m_img->m_data;
        $nPxl = ($this->m_gfh->m_nHeight - 1) * $this->m_gfh->m_nWidth;
        $bmp  = '';
        $nPad = ($this->m_gfh->m_nWidth % 4) ? 4 - ($this->m_gfh->m_nWidth % 4) : 0;
        for ($y = 0; $y < $this->m_gfh->m_nHeight; ++$y) {
            for ($x = 0; $x < $this->m_gfh->m_nWidth; ++$x, ++$nPxl) {
                if (($x >= $this->m_img->m_gih->m_nLeft)
                    && ($y >= $this->m_img->m_gih->m_nTop)
                    && ($x < ($this->m_img->m_gih->m_nLeft + $this->m_img->m_gih->m_nWidth))
                    && ($y < ($this->m_img->m_gih->m_nTop + $this->m_img->m_gih->m_nHeight))) {
                    // PART OF IMAGE
                    if ($this->m_img->m_bTrans && (ord($data[$nPxl]) == $this->m_img->m_nTrans)) {
                        // TRANSPARENT -> BACKGROUND
                        if (-1 == $bgColor) {
                            $bmp .= chr($this->m_gfh->m_nBgColor);
                        } else {
                            $bmp .= chr($bgColor);
                        }
                    } else {
                        $bmp .= $data[$nPxl];
                    }
                } else {
                    // BACKGROUND
                    if (-1 == $bgColor) {
                        $bmp .= chr($this->m_gfh->m_nBgColor);
                    } else {
                        $bmp .= chr($bgColor);
                    }
                }
            }
            $nPxl -= $this->m_gfh->m_nWidth << 1;

            // ADD PADDING
            for ($x = 0; $x < $nPad; ++$x) {
                $bmp .= "\x00";
            }
        }

        // BITMAPFILEHEADER
        $out .= 'BM';
        $out .= $this->dword(14 + 40 + ($nColors << 2) + mb_strlen($bmp));
        $out .= "\x00\x00";
        $out .= "\x00\x00";
        $out .= $this->dword(14 + 40 + ($nColors << 2));

        // BITMAPINFOHEADER
        $out .= $this->dword(40);
        $out .= $this->dword($this->m_gfh->m_nWidth);
        $out .= $this->dword($this->m_gfh->m_nHeight);
        $out .= "\x01\x00";
        $out .= "\x08\x00";
        $out .= "\x00\x00\x00\x00";
        $out .= "\x00\x00\x00\x00";
        $out .= "\x12\x0B\x00\x00";
        $out .= "\x12\x0B\x00\x00";
        $out .= $this->dword($nColors % 256);
        $out .= "\x00\x00\x00\x00";

        // COLOR TABLE
        if ($nColors > 0) {
            $out .= $rgbq;
        }

        // DATA
        $out .= $bmp;

        return $out;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $bgColor
     * @return bool|string
     */
    public function getPng($bgColor)
    {
        $out = '';

        if (!$this->m_bLoaded) {
            return false;
        }

        // PREPARE COLOR TABLE (RGBQUADs)
        if ($this->m_img->m_gih->m_bLocalClr) {
            $nColors = $this->m_img->m_gih->m_nTableSize;
            $pal     = $this->m_img->m_gih->m_colorTable->toString();
            if (-1 != $bgColor) {
                $bgColor = $this->m_img->m_gih->m_colorTable->colorIndex($bgColor);
            }
        } elseif ($this->m_gfh->m_bGlobalClr) {
            $nColors = $this->m_gfh->m_nTableSize;
            $pal     = $this->m_gfh->m_colorTable->toString();
            if (-1 != $bgColor) {
                $bgColor = $this->m_gfh->m_colorTable->colorIndex($bgColor);
            }
        } else {
            $nColors = 0;
            $bgColor = -1;
        }

        // PREPARE BITMAP BITS
        $data = $this->m_img->m_data;
        $nPxl = 0;
        $bmp  = '';
        for ($y = 0; $y < $this->m_gfh->m_nHeight; ++$y) {
            $bmp .= "\x00";
            for ($x = 0; $x < $this->m_gfh->m_nWidth; ++$x, ++$nPxl) {
                if (($x >= $this->m_img->m_gih->m_nLeft)
                    && ($y >= $this->m_img->m_gih->m_nTop)
                    && ($x < ($this->m_img->m_gih->m_nLeft + $this->m_img->m_gih->m_nWidth))
                    && ($y < ($this->m_img->m_gih->m_nTop + $this->m_img->m_gih->m_nHeight))) {
                    // PART OF IMAGE
                    $bmp .= $data[$nPxl];
                } else {
                    // BACKGROUND
                    if (-1 == $bgColor) {
                        $bmp .= chr($this->m_gfh->m_nBgColor);
                    } else {
                        $bmp .= chr($bgColor);
                    }
                }
            }
        }
        $bmp = gzcompress($bmp, 9);

        ///////////////////////////////////////////////////////////////////////
        // SIGNATURE
        $out .= "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
        ///////////////////////////////////////////////////////////////////////
        // HEADER
        $out .= "\x00\x00\x00\x0D";
        $tmp = 'IHDR';
        $tmp .= $this->ndword($this->m_gfh->m_nWidth);
        $tmp .= $this->ndword($this->m_gfh->m_nHeight);
        $tmp .= "\x08\x03\x00\x00\x00";
        $out .= $tmp;
        $out .= $this->ndword(crc32($tmp));
        ///////////////////////////////////////////////////////////////////////
        // PALETTE
        if ($nColors > 0) {
            $out .= $this->ndword($nColors * 3);
            $tmp = 'PLTE';
            $tmp .= $pal;
            $out .= $tmp;
            $out .= $this->ndword(crc32($tmp));
        }
        ///////////////////////////////////////////////////////////////////////
        // TRANSPARENCY
        if ($this->m_img->m_bTrans && ($nColors > 0)) {
            $out .= $this->ndword($nColors);
            $tmp = 'tRNS';
            for ($i = 0; $i < $nColors; ++$i) {
                $tmp .= ($i == $this->m_img->m_nTrans) ? "\x00" : "\xFF";
            }
            $out .= $tmp;
            $out .= $this->ndword(crc32($tmp));
        }
        ///////////////////////////////////////////////////////////////////////
        // DATA BITS
        $out .= $this->ndword(mb_strlen($bmp));
        $tmp = 'IDAT';
        $tmp .= $bmp;
        $out .= $tmp;
        $out .= $this->ndword(crc32($tmp));
        ///////////////////////////////////////////////////////////////////////
        // END OF FILE
        $out .= "\x00\x00\x00\x00IEND\xAE\x42\x60\x82";

        return $out;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $val
     * @return string
     */
    public function dword($val)
    {
        $val = (int)$val;

        return chr($val & 0xFF) . chr(($val & 0xFF00) >> 8) . chr(($val & 0xFF0000) >> 16) . chr(($val & 0xFF000000) >> 24);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $val
     * @return string
     */
    public function ndword($val)
    {
        $val = (int)$val;

        return chr(($val & 0xFF000000) >> 24) . chr(($val & 0xFF0000) >> 16) . chr(($val & 0xFF00) >> 8) . chr($val & 0xFF);
    }

    ///////////////////////////////////////////////////////////////////////////

    public function width()
    {
        return $this->m_gfh->m_nWidth;
    }

    ///////////////////////////////////////////////////////////////////////////

    public function height()
    {
        return $this->m_gfh->m_nHeight;
    }

    ///////////////////////////////////////////////////////////////////////////

    public function comment()
    {
        return $this->m_img->m_lpComm;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @return bool
     */
    public function loaded()
    {
        return $this->m_bLoaded;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////
