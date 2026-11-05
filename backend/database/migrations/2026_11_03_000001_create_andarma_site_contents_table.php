<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('andarma_site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('content_key', 150)->unique();
            $table->longText('content_value');
            $table->timestamps();
        });
        $values = json_decode(<<<'JSON'
{
  "inicio.p.1": "El comienzo de una gran historia merece el lugar correcto. Descubre espacios pensados para construir tu futuro.",
  "nosotros.p.1": "Conoce la visión que nos mueve y descubre cómo acompañamos cada proyecto: desde encontrar el lugar indicado hasta imaginar todo lo que puede llegar a ser.",
  "nosotros.p.2": "Desde 2018 impulsamos oportunidades en entornos semiboscosos, con una visión de comunidades planeadas, espacios de convivencia y respeto por la naturaleza.",
  "nosotros.p.3": "NUESTROS PILARES",
  "nosotros.p.4": "Terrenos en zonas de riqueza natural para disfrutar de un entorno con identidad.",
  "nosotros.p.5": "Una visión de desarrollo que contempla orden, convivencia y vida en comunidad.",
  "nosotros.p.6": "Acompañamiento para tomar decisiones informadas en cada etapa del proceso.",
  "nosotros.p.7": "Conoce nuestros desarrollos en zonas de riqueza natural, con alternativas para construir tu patrimonio y elegir el espacio ideal para tu familia.",
  "nosotros.p.8": "Además de la venta de terrenos y viviendas, ofrecemos orientación durante el proceso inmobiliario, con esquemas de pago y opciones que se revisan según cada proyecto.",
  "nosotros.p.9": "EXPLORA TUS POSIBILIDADES",
  "opciones.p.1": "Diseños funcionales y espacios para vivir con comodidad, tranquilidad y crear nuevas historias en familia.",
  "opciones.p.2": "Descubre alternativas de terrenos en ubicaciones con potencial, rodeadas de naturaleza y pensadas para tu próximo proyecto.",
  "por-que-andarma.p.1": "Nos distingue la atención personalizada, la seguridad en cada paso y el compromiso con crear comunidades en armonía con su entorno.",
  "como-comprar.p.1": "Te acompañamos en cada paso, desde la elección del terreno o vivienda hasta la entrega de tu nuevo patrimonio.",
  "como-comprar.h3.2": "Conoce",
  "como-comprar.p.3": "Explora nuestros desarrollos y elige la opción ideal para tus necesidades.",
  "como-comprar.h3.4": "Aparta",
  "como-comprar.p.5": "Reserva el terreno o vivienda que elegiste con un pago inicial.",
  "como-comprar.h3.6": "Financiamiento",
  "como-comprar.p.7": "Conoce las opciones y condiciones de pago disponibles para tu proyecto.",
  "como-comprar.p.8": "En proyectos de vivienda, construimos con atención a la calidad y cada detalle.",
  "como-comprar.h3.9": "Tu patrimonio",
  "como-comprar.p.10": "Recibe tu terreno o vivienda y comienza una nueva etapa.",
  "testimonios.p.1": "Cada nueva etapa comienza con una decisión importante. Queremos acompañarte en ese camino.",
  "testimonios.p.2": "“Buscábamos un espacio donde imaginar nuestro futuro. Tener información clara hizo más sencilla nuestra elección.”",
  "testimonios.p.3": "“Para nosotros era importante conocer cada etapa del proceso y saber qué opciones teníamos disponibles.”",
  "testimonios.p.4": "“Elegir dónde construir es una decisión grande. Nos ayudó comparar las alternativas con calma.”",
  "testimonios.p.5": "“Lo que más valoramos fue poder conocer las características del proyecto antes de decidir.”",
  "testimonios.p.6": "* Textos demostrativos para visualizar la sección; sustituir por testimonios y fotografías autorizados de clientes reales antes de publicar.",
  "contacto.h2.1": "Es momento de construir tu futuro",
  "contacto.p.2": "Escríbenos por WhatsApp y con gusto te asesoramos para encontrar el terreno o vivienda ideal.",
  "contacto.p.3": "El número oficial de atención se integrará al confirmar los datos de contacto."
}
JSON, true, 512, JSON_THROW_ON_ERROR);
        $now = now();
        foreach ($values as $key => $value) {
            DB::table('andarma_site_contents')->insert([
                'content_key' => $key,
                'content_value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
    public function down(): void { Schema::dropIfExists('andarma_site_contents'); }
};
