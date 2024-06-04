<?php
if (isset($_POST['url_list']) && isset($_POST['keyword'])) {
  $url_list = explode("\n", $_POST['url_list']);
  $keyword = $_POST['keyword'];

  $results = array();

  $num_urls = count($url_list);
  $current_url = 0;

  set_time_limit(300); // Set maximum execution time to 5 minutes

  $response = '';
  $response .= "URL,Keyword Available or Not,At the Line,Status,Current URL,Total URLs\n";

  foreach ($url_list as $url) {
    $url = trim($url); // Remove whitespace characters from URL
    // Add protocol prefix to URL if it's not present
    if (!preg_match('~^https?://~', $url)) {
      $url = 'http://' . $url;
    }

    $start_time = microtime(true); // Start timer

    $context = stream_context_create(array(
      'http' => array(
        'timeout' => 60 // Set timeout to 60 seconds
      )
    ));

    $html = @file_get_contents($url, false, $context);
    if ($html === false) {
      $status = 'Failed';
      $current_line = "No,HTTP request failed";
    } else {
      $dom = new DOMDocument();
      @$dom->loadHTML($html);
      $xpath = new DOMXPath($dom);
      $found = false;
      $line = ''; // Initialize $line variable
      foreach ($xpath->query("//*[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '$keyword')]") as $node) {
        $found = true;
        $line = $node->getNodePath();
        break;
      }

      // Check if the keyword is found
      if ($found) {
        $status = 'Found';
        $current_line = "Yes,$line";
      } else {
        $status = 'Not Found';
        $current_line = "No";
      }
    }
    //great

    $execution_time = microtime(true) - $start_time; // Calculate execution time

    if ($execution_time > 240) { // Check if execution time exceeds 4 minutes (leaving 1 minute for the rest of the script)
      $status = 'Skipped';
      $current_line = "Skipped,Maximum execution time reached";
      break; // Skip the rest of the URLs
    }

    $current_url++;
    $response .= "$url,$current_line,$status,$current_url,$num_urls\n";
  }

  // Generate CSV content
  $csv_content = "URL,Keyword Available or Not,At the Line,Status,Current URL,Total URLs\n";
  $csv_content .= $response;

  // Output CSV content as a downloadable file
  header('Content-Type: application/csv');
  header('Content-Disposition: attachment; filename="results.csv"');
  echo $csv_content;
  exit;
}
$processing = false;
// Output HTML form
?>
<html>

<head>
  <meta charset="UTF-8">
  <title>Keyword Finder</title>
  <style>
    #progress {
      width: 100%;
      height: 20px;
      background-color: #ddd;
    }

    #progress>div {
      height: 100%;
      background-color: #4CAF50;
    }
  </style>
</head>

<body>

  <form id="form" method="post">
    <label for="url_list">URL List (one URL per line)</label>
    <textarea name="url_list" id="url_list" cols="30" rows="10"></textarea>
    <br>
    <label for="keyword">Keyword</label>
    <input type="text" name="keyword" id="keyword">
    <br>
    <input type="submit" name="submit" value="Submit" id="submit-btn">
  </form>
  <div id="progress">
    <div style="width:0%;"></div>
  </div>
  <div id="result-container"></div>


  <script>
    const form = document.getElementById('form');
    const progressBar = document.getElementById('progress');
    const resultContainer = document.getElementById('result-container'); // Add a container for the result table

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const urlList = document.getElementById('url_list').value;
      const keyword = document.getElementById('keyword').value;

      document.getElementById('submit-btn').disabled = true;


      const xhr = new XMLHttpRequest();
      xhr.open('POST', '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.responseType = 'text'; // Set response type to text for table display

      xhr.upload.addEventListener('progress', (e) => {
        const percent = parseInt((e.loaded / e.total) * 100);
        progressBar.firstElementChild.style.width = `${percent}%`;

      });

      xhr.addEventListener('load', () => {
        progressBar.firstElementChild.style.width = '0%';
        document.getElementById('submit-btn').disabled = false;


        const response = xhr.responseText;
        const table = document.createElement('table');
        table.border = '1';
        const rows = response.split('\n');
        rows.shift(); // Remove the header row
        for (const row of rows) {
          const cols = row.split(',');
          const tr = document.createElement('tr');
          for (const col of cols) {
            const td = document.createElement('td');
            td.textContent = col;
            tr.appendChild(td);
          }
          table.appendChild(tr);
        }
        resultContainer.appendChild(table); // Add the table to the result container
        resultContainer.innerHTML = xhr.response;

        const csvBlob = new Blob([response], {
          type: 'text/csv'
        });
        const url = URL.createObjectURL(csvBlob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'results.csv';
        a.textContent = 'Download CSV';
        resultContainer.appendChild(a); // Add the download CSV button
      });

      xhr.send(`url_list=${urlList}&keyword=${keyword}`);
    });
  </script>

</body>

</html>