<?php session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
$permisos = explode(',', $_SESSION['user']['permisos']);
if (!in_array('calendario_liquidaciones', $permisos)) {
    header("Location: inicio.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <title>Detalle de futuras liquidaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <meta name="description" content="Descripción del sitio">
    <meta name="robots" content="index, follow">
    <meta http-equiv="x-ua-compatible" content="ie=edge" />

    <!-- FAVICON -->
    <link rel="icon" type="image/png" href="img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="img/favicon/favicon.svg" />
    <link rel="shortcut icon" href="img/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicon/apple-touch-icon.png" />
    <link rel="manifest" href="img/favicon/site.webmanifest" />

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/estilos.css">

</head>

<body>

<?php
include("includes/constants.php");
include("navigation.php");
?>

        <section class="top">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center">
                        <h1 class="color-clear w-arrow" id="Fecha">Detalle de futuras liquidaciones</h1>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table caption-top table-custom" id="transacciones">
                              <caption class="text-end" id="descargarXls"><a href="#" onClick="Detalle_Xls();"><i class="bi bi-box-arrow-down align-text-top"></i> Descargar como .xls </a></caption>
                              <thead id="tblhead">
                              
                              </thead>
                              <tbody id="tbldata">
                                
                              </tbody>
                            </table>
                        </div>

                        <p class="color-clear d-lg-none">Deslice la tabla a la izquierda para ver más detalles.</p>

                        <table class="table caption-top table-custom-lg table-auto">
                          <thead>
                            <tr class="bg-dark-blue">
                              <th scope="col" style="text-transform: uppercase;">Total Crédito fiscal del día:</th>
                              <th scope="col" id="totalCreditoFiscal"></th>
                            </tr>
                          </thead>
                        </table>
						
						 <table class="table caption-top table-custom-lg table-auto">
                          <thead>
                            <tr class="bg-dark-blue">
                              <th scope="col" style="text-transform: uppercase;">Total Ahorro CREDMOURA del día:</th>
                              <th scope="col" id="totalAhorroCREDMOURA"></th>
                            </tr>
                          </thead>
                        </table>
						
						<!--<table class="table caption-top table-custom-lg table-auto">
                          <thead>
                            <tr class="bg-dark-blue">
                              <th scope="col" style="text-transform: uppercase;">Recuperás:</th>
                              <th scope="col" id="totalAhorroCREDMOURAPorc"></th>
                            </tr>
                          </thead>
                        </table>-->
                        
                    </div>
                </div>

                <div class="row">
                    <div class="col-6" id="diaAnterior">
                        <button type="submit" class="btn btn-primary btn-sm"><img class="arrow-misc-btn" src="img/arrow-left.svg" alt=">"> Día Anterior</button>
                    </div>

                    <div class="col-6 text-end" id="diaSiguiente">
                        <button type="submit" class="btn btn-primary btn-sm">Día Siguiente <img class="arrow-misc-btn" src="img/arrow-right.svg" alt=">"></button>
                    </div>
                </div>

            </div>
        </section>

        <?php include("footer.php"); ?>


        <!-- Optional JavaScript -->
        <!-- jQuery first, then Popper.js, then Bootstrap JS -->
        <script src="js/jquery-3.6.0.min.js"></script>
        <script src="js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js" integrity="sha384-oBqDVmMz9ATKxIep9tiCxS/Z9fNfEXiDAYTujMAeBAsjFuCZSmKbSSUnQlmh/jp3" crossorigin="anonymous"></script>
        <script src="js/bootstrap.min.js"></script>

        <!-- Change Header -->
        <script src="js/changeheader.js"></script>

        <!-- Owl Carousel Assets -->
        <link href="js/owl-carousel/owl.carousel.css" rel="stylesheet">
        <link href="js/owl-carousel/owl.theme.css" rel="stylesheet">
        <script src="js/owl-carousel/owl.carousel.min.js"></script>

        <!-- Controller { -->

        <script>
          $(".toggle-button").click(function(){
              $('nav.main').slideToggle('fast');
          });
        </script>

        <script>
          // Inicializa todos los tooltips en la página
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
          var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
          })
		  
		  $('#descargarXls').hide();
		  $('#diaAnterior').hide();
		  $('#diaSiguiente').hide();
		  
		  var IdUnidadNegocio = <?php if ($_SESSION["user"]["idpdv"]==0) echo $_GET['IdUnidadNegocio']; else echo "'0'";?>;
		  var IdPDV = <?php if ($_SESSION["user"]["idpdv"]==0) echo $_GET['IdPDV']; else echo "'" . $_SESSION["user"]["idpdv"]. "'";?>;
		  
		  $.ajax({
			type: 'GET',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'Access-Control-Allow-Origin': '*'
			},
			url: '<?php echo(SCRIPT_ROOT)?>api/index2.php?table=liquidacion&metodo=detallefuturasliquidaciones&Desde=<?php echo $_GET["Fecha"]?>&IdUnidadNegocio='+IdUnidadNegocio+'&IdPDV='+IdPDV,
			dataType: "json",
			success: function(data) {
				var tableHead = $('#transacciones thead');
				var tableBody = $('#transacciones tbody');
				tableHead.empty();
				tableBody.empty();
				$('#transacciones').show();
				
				if(data.length > 0) {
					$('#Fecha').html("Detalle de futuras liquidaciones " + formatDate(data[0]["Fecha Liquidación"]));
					$('#descargarXls').show();
					tableHead.append('<tr>');
					for (var field in data[0]) {
						if (field=="Credito Fiscal" || field=="Fecha Liquidación" || field=="Ahorro CREDMOURA") continue;
						if (field=="Costo Financiación")
							tableHead.append('<th scope="col" width="5%">Costo Financiación</th>');
						else
							tableHead.append('<th scope="col" width="5%">' + field + '</th>');
					}
					tableHead.append('</tr>');
				}
				else{
					tableHead.append('<tr>');
					tableHead.append('<th scope="col" colspan="15">No hay registros.</th>');
					tableHead.append('</tr>');
				}
				
				totalVentas = 0;
				totalCostoServicio = 0;
				totalServiciosFinancieros = 0;
				totalBeneficioCREDMOURA = 0;
				totalArancelTarj = 0;
				totalIVA = 0;
				totalOtrosImp = 0;
				totalSubtotal = 0;
				totalCuentaComercio = 0;
				totalCtaCteMoura = 0;
				totalCreditoFiscalDia = 0;
				totalAhorroCREDMOURA = 0;
				total = 0;
				
				for (var i = 0; i < data.length; i++) {
					tableBody.append('<tr>');
					for (var field in data[i]) {
						if (field=="Credito Fiscal" || field=="Fecha Liquidación" || field=="Ahorro CREDMOURA") continue;
						
						if (field=="Fecha Venta")
							tableBody.append('<td scope="row">'+formatDate(data[i][field])+'</td>');
						else if (field=="Hora")
							tableBody.append('<td scope="row">'+formatTime(data[i][field])+'</td>');
						else if (field=="Venta" || field=="Costo de Servicio" || field=="Costo Financiación" || field=="Beneficio CREDMOURA" || field=="Arancel Tarjeta" || field=="IVA" || field=="Otros impuestos" || field=="Total" || field=="A Acred. CC Com." || field=="A Acred. CC Moura")
							tableBody.append('<td scope="row">'+formatCurrency(data[i][field])+'</td>');
						else
							tableBody.append('<td scope="row">'+data[i][field]+'</td>');
					}
					totalVentas = totalVentas + parseFloat(data[i]["Venta"]);
					totalCostoServicio = totalCostoServicio + parseFloat(data[i]["Costo de Servicio"]);
					totalServiciosFinancieros = totalServiciosFinancieros + parseFloat(data[i]["Costo Financiación"]);
					totalBeneficioCREDMOURA = totalBeneficioCREDMOURA + parseFloat(data[i]["Beneficio CREDMOURA"]);
					totalArancelTarj = totalArancelTarj + parseFloat(data[i]["Arancel Tarjeta"]);
					totalIVA = totalIVA + parseFloat(data[i]["IVA"]);
					totalOtrosImp = totalOtrosImp + parseFloat(data[i]["Otros impuestos"]);
					totalSubtotal = totalSubtotal + parseFloat(data[i]["Total"]);
					totalCuentaComercio = totalCuentaComercio + parseFloat(data[i]["A Acred. CC Com."]);
					totalCtaCteMoura = totalCtaCteMoura + parseFloat(data[i]["A Acred. CC Moura"]);
					totalCreditoFiscalDia = totalCreditoFiscalDia + parseFloat(data[i]["IVA"]);
					totalAhorroCREDMOURA = totalAhorroCREDMOURA + parseFloat(data[i]["Ahorro CREDMOURA"]);
					//total = total + parseFloat(data[i]["Total"]);
                    tableBody.append('</tr>');
				}

				tableBody.append('<tr class="bg-dark-blue">');
                tableBody.append('<th scope="row">TOTAL</th>');
                tableBody.append('<td>-</td>');
				tableBody.append('<td>-</td>');
                tableBody.append('<td>'+formatCurrency(totalVentas)+'</td>');
                tableBody.append('<td>'+formatCurrency(totalCostoServicio)+'</td>');
				tableBody.append('<td>'+formatCurrency(totalServiciosFinancieros)+'</td>');
				tableBody.append('<td>'+formatCurrency(totalArancelTarj)+'</td>');
				tableBody.append('<td>'+formatCurrency(totalIVA)+'</td>');
                tableBody.append('<td>'+formatCurrency(totalOtrosImp)+'</td>');
				tableBody.append('<td>'+formatCurrency(totalBeneficioCREDMOURA)+'</td>');
                tableBody.append('<td>-</td>');
                tableBody.append('<td>-</td>');
                tableBody.append('<td>'+formatCurrency(totalSubtotal)+'</td>');
                tableBody.append('<td>-</td>');
                tableBody.append('<td>'+formatCurrency(totalCuentaComercio)+'</td>');
                tableBody.append('<td>'+formatCurrency(totalCtaCteMoura)+'</td>');
				//tableBody.append('<td>'+formatCurrency(totalAhorroCREDMOURA)+'</td>');
				//tableBody.append('<td>'+formatCurrency(total)+'</td>');
                tableBody.append('</tr>');
				
				$('#totalCreditoFiscal').append(formatCurrency(totalCreditoFiscalDia));
				$('#totalAhorroCREDMOURA').append(formatCurrency(totalAhorroCREDMOURA));
				//$('#totalAhorroCREDMOURAPorc').append(formatNumber((totalAhorroCREDMOURA/totalVentas)*100) +"%");
			},
			error: function(err) {
				alert(err);
			}
		})
		
	function Detalle_Xls(){
		$.ajax({
			type: 'GET',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'Access-Control-Allow-Origin': '*'
			},
			url: '<?php echo(SCRIPT_ROOT)?>api/index2.php?table=liquidacion&metodo=detallefuturasliquidaciones&Desde=<?php echo $_GET["Fecha"]?>',
			dataType: "json",
			success: function(data) {
				var downloadLink;
				var tableContent = '';
				var dataType = 'application/vnd.ms-excel';
				
				if(data.length > 0) {
					totalVentas = 0;
					totalCostoServicio = 0;
					totalServiciosFinancieros = 0;
					totalBeneficioCREDMOURA = 0;
					totalArancelTarj = 0;
					totalOtrosImp = 0;
					totalSubtotal = 0;
					totalCuentaComercio = 0;
					totalCtaCteMoura = 0;
					totalCreditoFiscalDia = 0;
					totalAhorroCREDMOURA = 0;
					total = 0;
					
					tableContent = '<table id="tblExcel" style="width:100%" align="center" cellspacing="0" cellpadding="3" class="table-striped" ><thead id="tblhead"><thead id="tblhead"><tr>';
					
					for (var field in data[0]) {
						if (field=="Credito Fiscal" || field=="Fecha Liquidación" || field=="Ahorro CREDMOURA") continue;
						tableContent += '<th class="text-center" style="background-color:Orange; padding:10px;">' + field + '</th>';
					}
					tableContent += '</tr></thead>';
				
					tableContent += '<tbody id="tbldata">';
					for (var i = 0; i < data.length; i++) {
						tableContent += '<tr>';
						for (var field in data[i]) {
							if (field=="Credito Fiscal" || field=="Fecha Liquidación" || field=="Ahorro CREDMOURA") continue;
							if (field=="Fecha Venta")
								tableContent += '<td scope="row">'+formatDate(data[i][field])+'</td>';
							else if (field=="Hora")
								tableContent += '<td scope="row">'+formatTime(data[i][field])+'</td>';
							else if (field=="Venta" || field=="Costo de Servicio" || field=="Costo Financiación" || field=="Beneficio CREDMOURA" || field=="Arancel Tarjeta" || field=="IVA" || field=="Otros impuestos" || field=="Total" || field=="A Acred. CC Com." || field=="A Acred. CC Moura")
								tableContent += '<td scope="row">'+formatCurrency(data[i][field])+'</td>';
							else
								tableContent += '<td scope="row">'+data[i][field]+'</td>';
						}
						totalVentas = totalVentas + parseFloat(data[i]["Venta"]);
						totalCostoServicio = totalCostoServicio + parseFloat(data[i]["Costo de Servicio"]);
						totalServiciosFinancieros = totalServiciosFinancieros + parseFloat(data[i]["Costo Financiación"]);
						totalBeneficioCREDMOURA = totalBeneficioCREDMOURA + parseFloat(data[i]["Beneficio CREDMOURA"]);
						totalArancelTarj = totalArancelTarj + parseFloat(data[i]["Arancel Tarjeta"]);
						totalOtrosImp = totalOtrosImp + parseFloat(data[i]["Otros impuestos"]);
						totalSubtotal = totalSubtotal + parseFloat(data[i]["Total"]);
						totalCuentaComercio = totalCuentaComercio + parseFloat(data[i]["A Acred. CC Com."]);
						totalCtaCteMoura = totalCtaCteMoura + parseFloat(data[i]["A Acred. CC Moura"]);
						totalCreditoFiscalDia = totalCreditoFiscalDia + parseFloat(data[i]["IVA"]);
						totalAhorroCREDMOURA = totalAhorroCREDMOURA + parseFloat(data[i]["Ahorro CREDMOURA"]);
						//total = total + parseFloat(data[i]["Total"]);
						tableContent += '</tr>';
					}
					
					tableContent += '<tr class="bg-dark-blue">';
					tableContent += '<th scope="row">TOTAL</th>';
					tableContent += '<td>-</td>';
					tableContent += '<td>-</td>';
					tableContent += '<td>'+formatCurrency(totalVentas)+'</td>';
					tableContent += '<td>'+formatCurrency(totalCostoServicio)+'</td>';
					tableContent += '<td>'+formatCurrency(totalServiciosFinancieros)+'</td>';
					tableContent += '<td>'+formatCurrency(totalArancelTarj)+'</td>';
					tableContent += '<td>'+formatCurrency(totalIVA)+'</td>';
					tableContent += '<td>'+formatCurrency(totalOtrosImp)+'</td>';
					tableContent += '<td>'+formatCurrency(totalBeneficioCREDMOURA)+'</td>';
					tableContent += '<td>-</td>';
					tableContent += '<td>-</td>';
					tableContent += '<td>'+formatCurrency(totalSubtotal)+'</td>';
					tableContent += '<td>-</td>';
					tableContent += '<td>'+formatCurrency(totalCuentaComercio)+'</td>';
					tableContent += '<td>'+formatCurrency(totalCtaCteMoura)+'</td>';
					//tableContent += '<td>'+formatCurrency(totalAhorroCREDMOURA)+'</td>';
					//tableContent += '<td>'+formatCurrency(total)+'</td>';
					tableContent += '</tr>';
					
					tableContent += '<tr class="bg-dark-blue">';
					tableContent += '<th scope="row"></th>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					//tableContent += '<td></td>';
					//tableContent += '<td></td>';
					tableContent += '</tr>';
					
					tableContent += '<tr class="bg-dark-blue">';
					tableContent += '<th scope="row">Total Crédito fiscal del día:</th>';
					tableContent += '<td>'+formatCurrency(totalCreditoFiscalDia)+'</td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					//tableContent += '<td></td>';
					//tableContent += '<td></td>';
					tableContent += '</tr>';
					
					tableContent += '<tr class="bg-dark-blue">';
					tableContent += '<th scope="row">Total Ahorro CREDMOURA del día:</th>';
					tableContent += '<td>'+formatCurrency(totalAhorroCREDMOURA)+'</td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					//tableContent += '<td></td>';
					//tableContent += '<td></td>';
					tableContent += '</tr>';
					
					/*tableContent += '<tr class="bg-dark-blue">';
					tableContent += '<th scope="row">Recuperás:</th>';
					tableContent += '<td>'+formatNumber((totalAhorroCREDMOURA/totalVentas)*100) +'%</td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '<td></td>';
					tableContent += '</tr>';*/
					
					tableContent += '</tbody></table>';
				
					var tableHTML = '<html> ' +
									'<head> ' +
									'<meta http-equiv="content-type" content="text/plain; charset=UTF-8"/> ' +
									'</head> ' +
									'<body> ' + tableContent.replace(/ /g, '%20') +
									'</body> ' +
									'</html>';
					
					// Specify file name
					filename = 'Detalle futuras liquidaciones.xls';
					
					// Create download link element
					downloadLink = document.createElement("a");
					
					document.body.appendChild(downloadLink);
					
					if(navigator.msSaveOrOpenBlob){
						var blob = new Blob(['\ufeff', tableHTML], {
							type: dataType
						});
						navigator.msSaveOrOpenBlob( blob, filename);
					}else{
						// Create a link to the file
						downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
					
						// Setting the file name
						downloadLink.download = filename;
						
						//triggering the function
						downloadLink.click();
					}
				}
			},
			error: function(err) {
				alert(err);
			}
		})	
	}

function formatDate(date){
	dateConverted = new Date(date);
	day = dateConverted.getDate();
	month = dateConverted.getMonth() + 1;
	year = dateConverted.getFullYear();
								
	if(month < 10) month = "0" + month;
	if(day < 10) day = "0" + day;
	
	return `${day}/${month}/${year}`;
}

function formatTime(date){
	dateConverted = new Date(date);
	hour = dateConverted.getHours();
	minute = dateConverted.getMinutes();
	second = dateConverted.getSeconds();
	
	if(hour < 10) hour = "0" + hour;	
	if(minute < 10) minute = "0" + minute;
	if(second < 10) second = "0" + second;
	
	return `${hour}:${minute}:${second}`;
}

function formatCurrency(number){
	let ARPesos = new Intl.NumberFormat('es-AR', {
		style: 'currency',
		currency: 'ARS',
	});

	return `${ARPesos.format(number)}`;	
}

function formatNumber(number){
	
	return number.toLocaleString("es-ES", {minimumFractionDigits: 2, maximumFractionDigits: 2});	
}

</script>
		  
        </script>

    </body>
</html>