The legacy PDF API facade in src/pdf/Tcpdf.php is adapted from
https://github.com/tecnickcom/TCPDF/blob/main/tcpdf.php, version 7.0.21,
retrieved 2026-11-06. Copyright Nicola Asuni, Tecnick.com LTD (2002–2026),
licensed under LGPL-3.0-or-later; see LICENSE.TXT.

The package and legacy PDF engine are not installed. Rendering and PDF import
use tecnickcom/tc-lib-pdf. The facade is namespaced, retains the named arguments
used by applications, and the Pdf adapter supplies the former FPDI import API.
Generated font files belong in vendor and are prepared by the pdf-fonts script.
