<?php

namespace App\Services;

use Traversable;

/** PDF landscape tanpa dependency tambahan, mengikuti PDF writer existing project. */
class WarehouseSamplingHistoryCompactPdfService
{
    private const WIDTH = 842;
    private const HEIGHT = 595;

    public function render(array $filters, array $stats, iterable $rows): string
    {
        $list = $rows instanceof Traversable ? iterator_to_array($rows, false) : (is_array($rows) ? $rows : iterator_to_array($rows, false));
        $chunks = array_chunk($list, 14);
        if (! $chunks) $chunks = [[]];
        $pages = [];
        foreach ($chunks as $pageIndex => $chunk) {
            $content = [];
            $y = 565;
            $content[] = $this->text(24, $y, 'HISTORY SAMPLING GUDANG - LAPORAN RINGKAS', 12, true); $y -= 18;
            $content[] = $this->text(24, $y, 'Tutup: '.($filters['date_from'] ?: 'awal').' s/d '.($filters['date_to'] ?: 'akhir').' | '.($filters['source_database'] ?: 'Semua database'), 7, false); $y -= 17;
            $content[] = $this->text(24, $y, 'Periode '.$stats['periods'].' | Item '.$stats['items'].' | Baris '.$stats['rows'].' | Cocok '.$stats['match'].' | Selisih '.$stats['mismatch'], 7, true); $y -= 16;
            $content[] = $this->line(24, $y, 818, $y); $y -= 13;
            $heads = [24=>'Tutup',70=>'Gudang',160=>'Item',345=>'Validasi',395=>'Fisik(*)',444=>'Override(*)',505=>'Final',555=>'Selisih',605=>'Hasil',655=>'Checker',722=>'Admin / Catatan'];
            foreach ($heads as $x => $label) $content[] = $this->text($x,$y,$label,5.8,true);
            $y -= 9;
            $content[] = $this->line(24,$y,818,$y);$y-=13;
            foreach ($chunk as $row) {
                $validation=(float)($row->validation_system_qty??$row->warehouse_system_qty??0);
                $final=(float)($row->warehouse_physical_qty??0);
                $variance=$final-$validation;
                $content[]=$this->text(24,$y,$this->date($row->closed_at??null),5.5,false);
                $content[]=$this->text(70,$y,$this->short($row->source_database.' / '.$row->warehouse_code,18),5.4,false);
                $content[]=$this->text(160,$y,$this->short($row->item_code.' '.$row->item_name,37),5.2,false);
                $content[]=$this->text(345,$y,$this->qty($validation),5.6,false);
                $content[]=$this->text(395,$y,$this->qty((float)$row->checker_physical_total),5.6,false);
                $content[]=$this->text(444,$y,$row->admin_override_qty===null?'-':$this->qty((float)$row->admin_override_qty),5.6,false);
                $content[]=$this->text(505,$y,$this->qty($final),5.6,true);
                $content[]=$this->text(555,$y,$this->qty($variance),5.6,false);
                $content[]=$this->text(605,$y,$row->warehouse_result==='MATCH'?'COCOK':'SELISIH',5.5,true);
                $content[]=$this->text(655,$y,$this->short((string)($row->checker_name??'-'),12),5.2,false);
                $content[]=$this->text(722,$y,$this->short((string)($row->validator_name??'-'),15),5.2,false);
                $note=trim((string)($row->validation_note??''));
                if ($note!=='') {
                    $content[]=$this->text(160,$y-9,'Komentar Admin: '.$this->short($note,110),5.0,false);
                }
                $y-=29;
                $content[]=$this->line(24,$y+10,818,$y+10,0.91);
            }
            $content[] = $this->text(24,27,'(*) Fisik Checker dan Override adalah total gabungan item. Validasi dan Final adalah angka per gudang.',6.2,false);
            $content[] = $this->text(24,16,'Selisih = Qty Final Gudang - Qty Validasi Gudang. Catatan Admin dicetak bila ada.',5.8,false);
            $content[] = $this->text(766,16,'Hal '.($pageIndex+1).' / '.count($chunks),6,false);
            $pages[]=implode("\n",$content);
        }
        return $this->pdf($pages);
    }

    private function date(mixed $value): string
    {
        if (!$value) return '-';
        $ts=strtotime((string)$value);
        return $ts?date('d/m/Y',$ts):'-';
    }
    private function qty(float $n): string
    {
        $str=rtrim(rtrim(number_format($n,4,'.',','),'0'),'.');
        return $str===''||$str==='-0'?'0':$str;
    }
    private function short(string $s,int $len): string
    {
        $s=preg_replace('/\s+/u',' ',trim($s))??'';
        $s=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s)?:$s;
        return strlen($s)>$len?substr($s,0,$len-3).'...':$s;
    }
    private function text(float $x,float $y,string $string,float $size,bool $bold): string
    {
        $escaped=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)','', ' '],$this->short($string,500));
        return sprintf('BT /%s %.2F Tf 0 g %.2F %.2F Td (%s) Tj ET',$bold?'F2':'F1',$size,$x,$y,$escaped);
    }
    private function line(float $x1,float $y1,float $x2,float $y2,float $gray=.82): string
    {
        return sprintf('%.2F G 0.5 w %.2F %.2F m %.2F %.2F l S',$gray,$x1,$y1,$x2,$y2);
    }
    private function pdf(array $streams): string
    {
        $objs=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];
        $kids=[];$n=5;
        foreach($streams as $stream) {
            $page=$n++;$content=$n++;$kids[]="$page 0 R";
            $objs[$page]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::WIDTH.' '.self::HEIGHT."] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $content 0 R >>";
            $objs[$content]='<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream";
        }
        $objs[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',$kids).'] >>';
        ksort($objs);
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0=>0];
        foreach($objs as $id=>$obj){$offsets[$id]=strlen($pdf);$pdf.="$id 0 obj\n$obj\nendobj\n";}
        $at=strlen($pdf);$max=max(array_keys($objs));
        $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=$max;$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]??0);
        $pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n$at\n%%EOF";
        return $pdf;
    }
}
