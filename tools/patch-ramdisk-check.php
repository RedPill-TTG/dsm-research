<?php
declare(strict_types=1);

/**
 * A quick tool for patching the ramdisk check in the DSM kernel image
 * This lets you tinker with the initial ramdisk contents without disabling mount() features and modules loading
 *
 * Usage: php patch-ramdisk-check.php vmlinux vmlinux-mod
 */

function perr(string $txt, $die = false)
{
    fwrite(STDERR, $txt);
    if ($die) {
        die();
    }
}

if ($argc != 3) {
    perr("Usage: " . $argv[0] . " <inFile> <outFile>\n", true);
}

$file = realpath($argv[1]);
if (!is_file($file) || !$file) {
    perr("No file\n", true);
}

perr("\nGenerating patch for $file\n");

$rodataAddr = exec(sprintf('readelf -S \'%s\' | grep -E \'\.rodata\' | awk \'{ print $5 }\'', $file));
if (!$rodataAddr) {
    perr(".rodata not found\n", true);
}

$rdErrAddr = exec(
    sprintf(
        'readelf -p \'.rodata\' \'%s\' | grep \'3ramdisk corrupt\' | grep -oE \'\[(\s+)?.+\]\' | grep -oE \'[a-f0-9]+\'',
        $file
    )
);
if (!$rdErrAddr) {
    perr("ramdisk corrupt not found\n", true);
}


function decTo32bLEhex(int $dec)
{
    $hex = str_pad(dechex($dec), 32 / 8 * 2, 'f', STR_PAD_LEFT); //32-bit hex

    return implode('', array_reverse(str_split($hex, 2))); //make it little-endian
}

function decTo32bUFhex(int $dec)
{
    return implode(' ', str_split(str_pad(dechex($dec), 32 / 8 * 2, 'f', STR_PAD_LEFT), 2));
}

function hex2raw(string $hex)
{
    $bin = '';
    for ($i = 0, $iMax = strlen($hex); $i < $iMax; $i += 2) {
        $bin .= chr(hexdec($hex[$i] . $hex[$i + 1]));
    }

    return $bin;
}

//offsets will be 32 bit in ASM and in LE
$errPrintAddr = hexdec(substr($rodataAddr, -8)) + hexdec($rdErrAddr);
$errPrintCAddrLEH = decTo32bLEhex($errPrintAddr - 1); //Somehow rodata contains off-by-one sometimes...
$errPrintAddrLEH = decTo32bLEhex($errPrintAddr);

perr("LE arg addr: " . $errPrintCAddrLEH . "\n");

$fp = fopen('php://memory', 'r+');
fwrite($fp, file_get_contents($argv[1])); //poor man's mmap :D


const DIR_FWD = 1;
const DIR_RWD = -1;
function findSequence($fp, string $bin, int $pos, int $dir, int $maxToCheck)
{
    if ($maxToCheck === -1) {
        $maxToCheck = PHP_INT_MAX;
    }

    $len = strlen($bin);
    do {
        fseek($fp, $pos);
        if (strcmp(fread($fp, $len), $bin) === 0) {
            return $pos;
        }

        $pos = $pos + $dir;
        $maxToCheck--;
    } while (!feof($fp) && $pos != -1 && $maxToCheck != 0);

    return -1;
}


//Find the printk() call argument
$printkPos = findSequence($fp, hex2raw($errPrintCAddrLEH), 0, DIR_FWD, -1);
if ($printkPos === -1) {
    perr("printk pos not found!\n", true);
}
perr("Found pritk arg @ " . decTo32bUFhex($printkPos) . "\n");

//double check if it's a MOV reg,VAL (where reg is EAX/ECX/EDX/EBX/ESP/EBP/ESI/EDI)
fseek($fp, $printkPos - 3);
$instr = fread($fp, 3);
if (strncmp($instr, "\x48\xc7", 2) !== 0) {
    perr("Expected MOV=>reg before printk error, got " . bin2hex($instr) . "\n", true);
}
$dstReg = ord($instr[2]);
if ($dstReg < 192 || $dstReg > 199) {
    perr("Expected MOV w/reg operand [C0-C7], got " . bin2hex($instr[2]) . "\n", true);
}
$movPos = $printkPos - 3;
perr("Found printk MOV @ " . decTo32bUFhex($movPos) . "\n");

//now we should seek a reasonable amount (say, up to 32 bytes) for a sequence of CALL x => TEST EAX,EAX => JZ
$testPos = findSequence($fp, "\x85\xc0", $movPos, DIR_RWD, 32);
if ($testPos === -1) {
    perr("Failed to find TEST eax,eax\n", true);
}

$jzPos = $testPos + 2;
fseek($fp, $jzPos);
$jz = fread($fp, 2);
if ($jz[0] !== "\x74") {
    perr("Failed to find JZ\n", true);
}

$jzp = "\xEB" . $jz[1];
perr('OK - patching ' . bin2hex($jz) . " to " . bin2hex($jzp) . " @ $jzPos\n");
fseek($fp, $jzPos); //we should be here already
perr("Wrote " . fwrite($fp, $jzp) . " bytes to memory\n");

perr("Saving memory to " . $argv[2] . " ...\n");
$fp2 = fopen($argv[2], 'w');
fseek($fp, 0);
while (!feof($fp)) {
    fwrite($fp2, fread($fp, 8192));
}
fclose($fp2);
fclose($fp);
perr("DONE\n", true);
