<?php
declare(strict_types=1);

/**
 * Generate daily DSM password
 *
 * Algorithm (https://wrgms.com/synologys-secret-telnet-password/):
 *  - 1st character = month in hexadecimal, lower case (1=Jan, ... , a=Oct, b=Nov, c=Dec)
 *  - 2-3 = month in decimal, zero padded and starting in 1 (01, 02, 03, ..., 11, 12)
 *  - 4 = dash
 *  - 5-6 = day of the month in hex (01, 02 .., 0A, .., 1F)
 *  - 7-8 = greatest common divisor between month and day, zero padded. This is always a number between 01 and 12.
 *
 * @return string
 */
function getForDay(\DateTimeInterface $date)
{
    $monthNum = $date->format('n');
    $monthDay = $date->format('j');
    return sprintf('%x%02d-%02x%02d', $monthNum, $monthNum, $monthDay, gmp_gcd($monthNum, $monthDay));
}

function printForDay(\DateTimeInterface $date)
{
    printf("Password for %s: %s\n", $date->format('m/F d'), getForDay($date));
}

switch ($argv[1] ?? null) {
    case '-a':
        $date = DateTime::createFromFormat('z-Y', '0-2000'); //Use some leap year
        $day = new DateInterval('P1D');

        for($i=1; $i<=366; $i++) {
            printForDay($date);
            $date->add($day);
        }
        break;

    case '-3':
        printForDay(new \DateTimeImmutable('yesterday 00:00'));
        printForDay(new \DateTimeImmutable());
        printForDay(new \DateTimeImmutable('tomorrow 00:00'));
        break;

    default:
        printForDay(new \DateTime()); //Print for today

        printf("%s\n", str_repeat('-', 50));
        echo "Tip: run with the following options\n" .
             "\t-a to print all daily password for the whole year\n" .
             "\t-3 to print 3-days with today as center (for TZ difference)\n";
}
