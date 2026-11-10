<?php

namespace App\Support;

use InvalidArgumentException;

/** Periode laporan tidak valid; pesannya berbahasa Indonesia dan siap ditampilkan ke user. */
final class InvalidReportPeriod extends InvalidArgumentException {}
