<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DiseasesGroupSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('diseases_group')->upsert([
            ['cd' => '001', 'name_th' => "โรคเบาหวาน", 'name_en' => "Diabetes mellitus", 'score' => '4.86'],
            ['cd' => '002', 'name_th' => "โรคความดันโลหิตสูง", 'name_en' => "Hypertension", 'score' => '3.42'],
            ['cd' => '003', 'name_th' => "โรคตับอักเสบเรื้อรังและโรคตับแข็ง", 'name_en' => "Chronic hepatitis and Cirrhosis of liver", 'score' => '3.83'],
            ['cd' => '004', 'name_th' => "โรคภาวะหัวใจล้มเหลว", 'name_en' => "Congestive Heart failure", 'score' => '6.02'],
            ['cd' => '005', 'name_th' => "โรคเส้นเลือดสมองแตก/อุดตัน", 'name_en' => "Cerebrovascular accident", 'score' => '4.47'],
            ['cd' => '006', 'name_th' => "โรคมะเร็ง", 'name_en' => "Malignancy", 'score' => '0.74'],
            ['cd' => '007', 'name_th' => "โรคภูมิคุ้มกันบกพร่อง", 'name_en' => "AIDS", 'score' => '7.10'],
            ['cd' => '008', 'name_th' => "โรคถุงลมโป่งพอง", 'name_en' => "Emphysema", 'score' => '3.84'],
            ['cd' => '009', 'name_th' => "โรคไตวายเรื้อรัง", 'name_en' => "Chronic renal failure", 'score' => '8.74'],
            ['cd' => '010', 'name_th' => "โรคพาร์กินซั่น", 'name_en' => "Parkinson's disease", 'score' => '0.74'],
            ['cd' => '011', 'name_th' => "โรคมายแอสทีเนีย เกรวิส", 'name_en' => "Myasthenia gravis", 'score' => '5.94'],
            ['cd' => '012', 'name_th' => "โรคเบาจืด", 'name_en' => "Diabetes insipidus", 'score' => '0.87'],
            ['cd' => '013', 'name_th' => "โรคมัลติเพิลสเคลอโรสิส", 'name_en' => "Multiple sclerosis", 'score' => '17.17'],
            ['cd' => '014', 'name_th' => "โรคไขมันในเลือดสูง", 'name_en' => "Dyslipidemia", 'score' => '0.37'],
            ['cd' => '015', 'name_th' => "โรคข้ออักเสบรูมาตอยด์", 'name_en' => "Rheumatoid arthritis", 'score' => '4.69'],
            ['cd' => '016', 'name_th' => "โรคต้อหิน", 'name_en' => "Glaucoma", 'score' => '4.62'],
            ['cd' => '017', 'name_th' => "โรคไต เนฟโฟรติค", 'name_en' => "Nephrotic syndrome", 'score' => '5.33'],
            ['cd' => '018', 'name_th' => "โรคลูปัส", 'name_en' => "SLE", 'score' => '6.39'],
            ['cd' => '019', 'name_th' => "โรคเลือดอะพลาสติก", 'name_en' => "Aplastic anemia", 'score' => '5.68'],
            ['cd' => '020', 'name_th' => "โรคทาลาสซีเมีย", 'name_en' => "Thalassemia", 'score' => '0.27'],
            ['cd' => '021', 'name_th' => "โรคฮีโมฟีลเลีย", 'name_en' => "Hemophilia", 'score' => '1.25'],
            ['cd' => '022', 'name_th' => "โรคเรื้อนกวาง", 'name_en' => "Psoriasis", 'score' => '3.34'],
            ['cd' => '023', 'name_th' => "โรคผิวหนังพุพองเรื้อรัง", 'name_en' => "Chronic vesiculobullous disease", 'score' => '1.70'],
            ['cd' => '024', 'name_th' => "โรคเลือด ไอทีพี", 'name_en' => "ITP", 'score' => '3.74'],
            ['cd' => '025', 'name_th' => "โรคต่อมไทรอยด์เป็นพิษ", 'name_en' => "Thyrotoxicosis", 'score' => '2.02'],
            ['cd' => '026', 'name_th' => "โรคจิต", 'name_en' => "Schizophrenia / Delusional disorders", 'score' => '2.02'],
        ], ['cd'], ['name_th', 'name_en', 'score']);
    }
}
