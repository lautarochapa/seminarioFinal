<?php

use Illuminate\Database\Seeder;

class MarketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('markets')->insert([ 
            'nombre' => 'disco', 
            'color' => '#fe0002', 
            'telefono' => '0810-777-8888', 
            'pagina' => 'https://www.disco.com.ar/Login/PreHome.aspx', 
            'descuento' => 'https://www.disco.com.ar/Descuentos/Descuentos.aspx', 
            'prod' => '0', 
            'img_1' => 'https://www.disco.com.ar/DiscoComprasArchivos/Archivos/', 
            'img_2' => '0', 
            ]);
    
            DB::table('markets')->insert([ 
                'nombre' => 'vea', 
                'color' => '#5eb43e', 
                'telefono' => '0810-999-9832', 
                'pagina' => 'https://www.veadigital.com.ar/', 
                'descuento' => 'https://www.veadigital.com.ar/Descuentos/Descuentos.aspx', 
                'prod' => '0', 
                'img_1' => 'https://www.veadigital.com.ar/VeaComprasArchivos/Archivos/', 
                'img_2' => '0', 
                ]);

                DB::table('markets')->insert([ 
                    'nombre' => 'walmart', 
                    'color' => '#007DC6', 
                    'telefono' => '0810-444-9256', 
                    'pagina' => 'https://www.walmart.com.ar/', 
                    'descuento' => '0', 
                    'prod' => 'https://www.walmart.com.ar/', 
                    'img_1' => 'http://walmartar.vteximg.com.br/arquivos/ids/', 
                    'img_2' => '0', 
                    ]);

                    DB::table('markets')->insert([ 
                        'nombre' => 'jumbo', 
                        'color' => '#1FA02E', 
                        'telefono' => '0810-999-58626', 
                        'pagina' => 'https://www.jumbo.com.ar/', 
                        'descuento' => 'https://www.jumbo.com.ar/descuentos?dia-martes', 
                        'prod' => 'https://www.jumbo.com.ar/', 
                        'img_1' => 'https://jumboargentina.vteximg.com.br/arquivos/ids/', 
                        'img_2' => 'Comensal', 
                        ]);

                        DB::table('markets')->insert([ 
                            'nombre' => 'carrefour', 
                            'color' => '#2e8ab8', 
                            'telefono' => '0800-444-8484', 
                            'pagina' => 'https://www.carrefour.com.ar/', 
                            'descuento' => '0', 
                            'prod' => 'https://supermercado.carrefour.com.ar/', 
                            'img_1' => 'https://supermercado.carrefour.com.ar/media/catalog/product/cache/1/small_image/214x/9df78eab33525d08d6e5fb8d27136e95/', 
                            'img_2' => 'https://supermercado.carrefour.com.ar/media/catalog/product/', 
                            ]);

                            DB::table('markets')->insert([ 
                                'nombre' => 'dia', 
                                'color' => '#d52b1e', 
                                'telefono' => '0810-222-5316', 
                                'pagina' => 'https://diaonline.supermercadosdia.com.ar/', 
                                'descuento' => 'https://www.supermercadosdia.com.ar/medios-de-pago-y-promociones/', 
                                'prod' => 'https://diaonline.supermercadosdia.com.ar/', 
                                'img_1' => 'https://ardiaqa.vteximg.com.br/arquivos/ids/', 
                                'img_2' => '0', 
                                ]);

                                DB::table('markets')->insert([ 
                                    'nombre' => 'coto', 
                                    'color' => '#47b8d6', 
                                    'telefono' => '0810-888-2686', 
                                    'pagina' => 'https://www.cotodigital3.com.ar/sitios/cdigi/', 
                                    'descuento' => 'https://www.cotodigital3.com.ar/sitios/cdigi/promo/promotions.jsp', 
                                    'prod' => 'https://www.cotodigital3.com.ar/sitios/cdigi/producto/', 
                                    'img_1' => 'http://static.cotodigital.com.ar/sitios/fotos/medium/', 
                                    'img_2' => 'http://static.cotodigital.com.ar/sitios/fotos/large/', 
                                    ]);
    
    
    
    
    
    }

}
