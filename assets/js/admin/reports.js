


/*
|--------------------------------------------------------------------------
| EVENT STATUS CHART
|--------------------------------------------------------------------------
*/

const statusCanvas =
    document.getElementById('eventStatusChart');


new Chart(statusCanvas, {

    type: 'doughnut',

    data: {

        labels: [
            'Upcoming',
            'Ongoing',
            'Ended'
        ],

        datasets: [{

            data: [
                upcomingEvents,
                ongoingEvents,
                endedEvents
            ],

            backgroundColor: [
                '#0d6efd',
                '#198754',
                '#6c757d'
            ]

        }]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        plugins: {

            legend: {

                position: 'bottom'

            }

        }

    }

});


/*
|--------------------------------------------------------------------------
| CATEGORY CHART
|--------------------------------------------------------------------------
*/




const categoryCanvas =
    document.getElementById('categoryChart');


new Chart(categoryCanvas, {

    type: 'bar',

    data: {

        labels: categoryLabels,

        datasets: [{

            label: 'Number of Events',

            data: categoryData

        }]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        scales: {

            y: {

                beginAtZero: true,

                ticks: {

                    precision: 0

                }

            }

        },

        plugins: {

            legend: {

                display: false

            }

        }

    }

});
