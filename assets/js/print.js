        function printTextarea() {
            const textotodo = document.getElementById('code').value;
            const textoFormatado = textotodo.replace(/\n/g, '<br>');
            const printWindow = window.open('', '_blank', 'width=600,height=400');
            printWindow.document.open();
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title></title>
                </head>
                <body>
                <div>${textoFormatado}</div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
