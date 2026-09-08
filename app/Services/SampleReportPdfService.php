<?php

namespace App\Services;

use Traversable;

class SampleReportPdfService
{
    public const CONTENT_TYPE = 'application/pdf';
    private const W = 595.0;
    private const H = 842.0;
    private const L = 28.0;
    private const R = 567.0;

    public function render(array $filters, array $stats, ?array $coverage, iterable $rows): string
    {
        $list = $rows instanceof Traversable ? iterator_to_array($rows, false) : (is_array($rows) ? $rows : iterator_to_array($rows, false));
        $pages = [];
        $chunks = array_chunk($list, 28);
        if ($chunks === []) $chunks = [[]];

        foreach ($chunks as $pageIndex => $chunk) {
            $c = [];
            $y = 808.0;
            $c[] = $this->text(self::L, $y, 'LAPORAN SAMPLING STOK GERAI', 14, true); $y -= 18;
            $c[] = $this->text(self::L, $y, 'Periode: '.$filters['date_from'].' s/d '.$filters['date_to'].' | DB: '.($filters['source_database'] ?: 'Semua').' | Warehouse ID: '.($filters['erp_warehouse_id'] ?: 'Semua'), 7, false); $y -= 18;
            $summary = 'Total '.$stats['total'].' | Item unik '.$stats['unique_items'].' | Cocok '.$stats['match'].' | Tidak cocok '.$stats['mismatch'].' | User '.$stats['users'];
            $c[] = $this->text(self::L, $y, $summary, 7, true); $y -= 15;
            if ($coverage) {
                $c[] = $this->text(self::L, $y, 'Coverage: '.$coverage['completed'].' / '.$coverage['target'].' item = '.number_format($coverage['percentage'], 2).'% (target item stok > 0 per tanggal akhir filter)', 7, true); $y -= 18;
            }
            $c[] = $this->line(self::L, $y, self::R, $y); $y -= 14;
            $c[] = $this->text(self::L, $y, 'Waktu', 6, true);
            $c[] = $this->text(92, $y, 'User / Lokasi', 6, true);
            $c[] = $this->text(205, $y, 'Item', 6, true);
            $c[] = $this->text(390, $y, 'Sistem', 6, true);
            $c[] = $this->text(435, $y, 'Fisik', 6, true);
            $c[] = $this->text(480, $y, 'Var', 6, true);
            $c[] = $this->text(526, $y, 'Hasil', 6, true); $y -= 10;
            $c[] = $this->line(self::L, $y, self::R, $y); $y -= 12;

            foreach ($chunk as $row) {
                $sys=(float)$row->system_qty; $phy=(float)$row->physical_qty;
                $c[]=$this->text(self::L,$y,$row->scanned_at?->format('d/m H:i') ?? '-',5.7,false);
                $c[]=$this->text(92,$y,$this->plain(($row->user?->name ?? '-').' / '.$row->location,26),5.7,false);
                $c[]=$this->text(205,$y,$this->plain($row->item_code.' '.$row->item_name,42),5.7,false);
                $c[]=$this->text(390,$y,$this->qty($sys),5.7,false);
                $c[]=$this->text(435,$y,$this->qty($phy),5.7,false);
                $c[]=$this->text(480,$y,$this->qty($phy-$sys),5.7,false);
                $c[]=$this->text(526,$y,$row->result==='MATCH'?'COCOK':'SELISIH',5.5,true);
                $y-=22;
                $c[]=$this->line(self::L,$y+8,self::R,$y+8,0.92);
            }
            $c[]=$this->text(self::L,18,'Sampling report - tidak memiliki kolom komentar. Data diambil dari hasil sampling tersimpan.',5.5,false);
            $c[]=$this->text(520,18,'Hal '.($pageIndex+1).' / '.count($chunks),5.5,false);
            $pages[] = implode("\n", $c);
        }

        return $this->buildPdf($pages);
    }

    private function qty(float $v): string { return rtrim(rtrim(number_format($v,4,'.',','),'0'),'.') ?: '0'; }
    private function plain(string $v, int $max): string { $v=preg_replace('/\s+/u',' ',trim($v))??''; $v=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$v) ?: $v; return strlen($v)>$max?substr($v,0,$max-3).'...':$v; }
    private function esc(string $v): string { return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)','', ' '],$v); }
    private function text(float $x,float $y,string $t,float $s,bool $b): string { return sprintf('BT /%s %.2F Tf 0 g %.2F %.2F Td (%s) Tj ET',$b?'F2':'F1',$s,$x,$y,$this->esc($this->plain($t,500))); }
    private function line(float $x1,float $y1,float $x2,float $y2,float $g=0.82): string { return sprintf('%.2F G 0.5 w %.2F %.2F m %.2F %.2F l S',$g,$x1,$y1,$x2,$y2); }

    /** @param list<string> $streams */
    private function buildPdf(array $streams): string
    {
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];
        $kids=[];$next=5;
        foreach($streams as $stream){$p=$next++;$co=$next++;$kids[]="$p 0 R";$objects[$p]=sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0F %.0F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',self::W,self::H,$co);$objects[$co]="<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream";}
        $objects[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$off=[0=>0];foreach($objects as $n=>$o){$off[$n]=strlen($pdf);$pdf.="$n 0 obj\n$o\nendobj\n";}
        $xref=strlen($pdf);$max=max(array_keys($objects));$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf("%010d 00000 n \n",$off[$i]??0);$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";return $pdf;
    }
}
