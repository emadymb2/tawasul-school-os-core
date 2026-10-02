(function () {
    $(document).ready(function() {

        // If an element with id "status" is found, do the version check
        // Supposed to only have one "#status" in the page, so this is only run once.
        $("#status").first().each(function () {
            var $status = $(this);
            var $edgeIndicator = $('#cuttingEdgeCode');
            var $edgeHiddenInput = $("input[name=cuttingEdgeCodeHidden]");

            // environment check
            var tawasulinstallerError = false;
            if (typeof tawasulinstaller === 'undefined') {
                console.error('Unable to find tawasulinstaller in global variables');
                tawasulinstallerError = true;
            } else if (typeof tawasulinstaller.version === 'undefined') {
                console.error('No tawasul version is specified in the environment');
                tawasulinstallerError = true;
            } else if (typeof tawasulinstaller.msg === 'undefined') {
                console.error('Translation function tawasulinstaller.msg() does not exits.');
                tawasulinstallerError = true;
            }
            if (tawasulinstallerError) {
                $status.attr("class", "error");
                $status.html("Cutting Edge Code check: Unexpected javascript error.");
                return;
            }

            // cutting edge code check
            $.ajax({
                crossDomain: true,
                type:"GET",
                url: "https://tos.fiksutiliratkaisut.fi/services/version/devCheck.php?version=" + tawasulinstaller.version + "&callback=?",
                dataType: "jsonp",
                jsonpCallback: 'fnsuccesscallback',
                jsonpResult: 'jsonpResult',
                success: function(data) {
                    $status.attr("class", "success");
                    if (data['status'] === 'false') {
                        $status.html(tawasulinstaller.msg('__edge_code_check_success__')) ;
                    } else {
                        $status.html(tawasulinstaller.msg('__edge_code_check_success__')) ;
                        $edgeIndicator.val('Yes');
                        $edgeHiddenInput.val('Y');
                    }
                },
                error: function(data, textStatus, errorThrown) {
                    $status.attr("class", "error");
                    $status.html(tawasulinstaller.msg('__edge_code_check_failed__')) ;
                }
            });
        });
    });
})();
