<h1 class="mb-4">Dashboard</h1>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Total de Productos</h5>
                <p class="display-4"><?= $totales['total_productos'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Total de Rutas</h5>
                <p class="display-4"><?= $totales['total_rutas'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Total de Despachos</h5>
                <p class="display-4"><?= $totales['total_despachos'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Total Vendido</h5>
                <p class="display-4"><?= $totales['total_vendido'] ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Porcentaje de Productos Retornados</h5>
            </div>
            <div class="card-body">
                <canvas id="chartRetorno"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Productos Más Vendidos</h5>
            </div>
            <div class="card-body">
                <canvas id="chartVendidos"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Rutas con Más Ventas</h5>
            </div>
            <div class="card-body">
                <canvas id="chartRutas"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Datos para la gráfica de retornos
    const retornoData = {
        labels: <?= json_encode(array_column($dashboardData['productos_retorno'], 'nombre')) ?>,
        datasets: [{
            label: 'Porcentaje de Retorno',
            data: <?= json_encode(array_column($dashboardData['productos_retorno'], 'porcentaje')) ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.5)',
                'rgba(54, 162, 235, 0.5)',
                'rgba(255, 206, 86, 0.5)',
                'rgba(75, 192, 192, 0.5)',
                'rgba(153, 102, 255, 0.5)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)'
            ],
            borderWidth: 1
        }]
    };
    
    // Datos para la gráfica de productos vendidos
    const vendidosData = {
        labels: <?= json_encode(array_column($dashboardData['productos_vendidos'], 'nombre')) ?>,
        datasets: [{
            label: 'Total Vendido',
            data: <?= json_encode(array_column($dashboardData['productos_vendidos'], 'total')) ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.5)',
                'rgba(54, 162, 235, 0.5)',
                'rgba(255, 206, 86, 0.5)',
                'rgba(75, 192, 192, 0.5)',
                'rgba(153, 102, 255, 0.5)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)'
            ],
            borderWidth: 1
        }]
    };
    
    // Datos para la gráfica de rutas
    const rutasData = {
        labels: <?= json_encode(array_column($dashboardData['rutas_ventas'], 'nombre')) ?>,
        datasets: [{
            label: 'Total Vendido',
            data: <?= json_encode(array_column($dashboardData['rutas_ventas'], 'total')) ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.5)',
                'rgba(54, 162, 235, 0.5)',
                'rgba(255, 206, 86, 0.5)',
                'rgba(75, 192, 192, 0.5)',
                'rgba(153, 102, 255, 0.5)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)'
            ],
            borderWidth: 1
        }]
    };
    
    // Configuración de las gráficas
    const configRetorno = {
        type: 'pie',
        data: retornoData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.raw.toFixed(2)}%`;
                        }
                    }
                }
            }
        }
    };
    
    const configVendidos = {
        type: 'bar',
        data: vendidosData,
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    };
    
    const configRutas = {
        type: 'bar',
        data: rutasData,
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    };
    
    // Renderizar gráficas
    new Chart(document.getElementById('chartRetorno'), configRetorno);
    new Chart(document.getElementById('chartVendidos'), configVendidos);
    new Chart(document.getElementById('chartRutas'), configRutas);
});
</script>