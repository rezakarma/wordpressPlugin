jQuery(document).ready(function($) {
    // Handle Excel file upload
    $('#upload-excel').on('click', function(e) {
        e.preventDefault();
        
        var fileInput = $('#excel_file')[0];
        if (!fileInput.files.length) {
            alert('Please select an Excel file');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'dekapost_upload_excel');
        formData.append('nonce', dekapostShipping.nonce);
        formData.append('excel_file', fileInput.files[0]);
        formData.append('city_id', $('#city').val());
        formData.append('contract_id', $('#contract').val());

        $.ajax({
            url: dekapostShipping.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error uploading file');
            }
        });
    });

    // Handle submit parcels
    $('#submit-parcels').on('click', function() {
        var selectedParcels = [];
        $('.parcel-checkbox:checked').each(function() {
            selectedParcels.push($(this).data('parcel'));
        });

        if (selectedParcels.length === 0) {
            alert('Please select at least one parcel');
            return;
        }

        $.ajax({
            url: dekapostShipping.ajaxurl,
            type: 'POST',
            data: {
                action: 'dekapost_save_parcels',
                nonce: dekapostShipping.nonce,
                parcels_data: JSON.stringify(selectedParcels)
            },
            success: function(response) {
                if (response.success) {
                    alert('Parcels submitted successfully');
                    // Remove submitted parcels from the table
                    $('.parcel-checkbox:checked').closest('tr').remove();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error submitting parcels');
            }
        });
    });

    // Handle delete selected
    $('#delete-selected').on('click', function() {
        if (confirm('Are you sure you want to delete the selected parcels?')) {
            $('.parcel-checkbox:checked').closest('tr').remove();
        }
    });

    // Handle select all checkbox
    $('#select-all-parcels').on('change', function() {
        $('.parcel-checkbox').prop('checked', $(this).prop('checked'));
    });

    // City change handler
    $('#city').on('change', function() {
        var cityId = $(this).val();
        if (cityId) {
            // You might want to load contracts based on selected city
            // This would require an additional AJAX call
        }
    });

    // Function to display results
    function displayResults(data) {
        var $table = $('.results-table-container');
        var $tbody = $('#parcels-results');
        
        $tbody.empty();
        
        if (data && data.data) {
            data.data.forEach(function(parcel, index) {
                var row = $('<tr>');
                row.append('<td><input type="checkbox" class="parcel-checkbox" data-parcel=\'' + JSON.stringify(parcel) + '\'></td>');
                row.append('<td>' + parcel.weight + 'g</td>');
                row.append('<td>' + $('#city option:selected').text() + '</td>');
                row.append('<td>' + $('#city option:selected').text() + '</td>');
                row.append('<td>' + parcel.totalAmount + '</td>');
                row.append('<td>' + parcel.message + '</td>');
                $tbody.append(row);
            });
            
            $table.show();
        }
    }
}); 