var data = {'type': payment.type};

if (payment.type == 'credits') {
    data.quantity = payment.quantity;
} else if (payment.type == 'package') {
    data.package_id = payment.package.id;
}

axios.post('/store/payreks/session', data).then((res) => {
    if (res.data.success) {
        Swal.close();

        Swal.fire({
            title: 'Ödeme yöntemi seçin.',
            input: 'select',
            inputOptions: JSON.parse(res.data.inputOptions),
            showCancelButton: true,
            confirmButtonText: 'Ödeme',
            showLoaderOnConfirm: false,
            preConfirm: (ddata) => {
                data.gateway = ddata;
                return axios.post('/store/payreks/order', data).then((res) => {
                    if (res.data.success) {
                        Swal.close();
                        if (res.data.link) {
                            window.location.replace(res.data.link);
                        }
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: res.data.message
                        })
                    }
                })
            },
            allowOutsideClick: () => !Swal.isLoading()
        });
    } else {
        swal({
            type: 'error',
            title: 'Error',
            text: res.data.message
        })
    }
})

/*$(document).ready(function(){
    $("*").click(function(event){
        if (!$(event.target).is("#payxIframeModal")) {
            $('#payxIframeModal').modal('hide');
            $('#payxIframeModal').remove();
            return false;
        }
    });
});*/
