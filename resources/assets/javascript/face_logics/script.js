var labels = [];
let detectedFaces = [];
let sendingData = false;

function updateTable() {
  var selectedCourseID = document.getElementById("courseSelect").value;
  var selectedUnitCode = document.getElementById("unitSelect").value;
  var selectedVenue = document.getElementById("venueSelect").value;
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "resources/pages/lecture/manageFolder.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4 && xhr.status === 200) {
      var response = JSON.parse(xhr.responseText);
      if (response.status === "success") {
        labels = response.data;

        if (selectedCourseID && selectedUnitCode && selectedVenue) {
          updateOtherElements();
        }
        document.getElementById("studentTableContainer").innerHTML =
          response.html;
      } else {
        console.error("Error:", response.message);
      }
    }
  };
  xhr.send(
    "courseID=" +
      encodeURIComponent(selectedCourseID) +
      "&unitID=" +
      encodeURIComponent(selectedUnitCode) +
      "&venueID=" +
      encodeURIComponent(selectedVenue)
  );
}

function markAttendance(detectedFaces) {
  document.querySelectorAll("#studentTableContainer tr").forEach((row, index) => {
    if (index === 0) return; // Skip header row
    
    const registrationNumber = row.cells[0].innerText.trim();
    const statusCell = row.cells[5]; // Adjust if needed
    
    if (detectedFaces.includes(registrationNumber)) {
      statusCell.innerText = "Present";
      statusCell.style.backgroundColor = "#d4edda";
      statusCell.style.color = "#155724";
    } else {
      statusCell.innerText = "Absent";
      statusCell.style.backgroundColor = "#f8d7da";
      statusCell.style.color = "#721c24";
    }
  });
}

function updateOtherElements() {
  const video = document.getElementById("video");
  const videoContainer = document.querySelector(".video-container");
  const startButton = document.getElementById("startButton");
  const endButton = document.getElementById("endButton");
  let webcamStarted = false;
  let modelsLoaded = false;

  Promise.all([
    faceapi.nets.ssdMobilenetv1.loadFromUri("models"),
    faceapi.nets.faceRecognitionNet.loadFromUri("models"),
    faceapi.nets.faceLandmark68Net.loadFromUri("models"),
  ])
    .then(() => {
      modelsLoaded = true;
      console.log("Models loaded successfully");
      showMessage("Face recognition models loaded successfully!");
    })
    .catch((error) => {
      alert("Models not loaded, please check your model folder location");
      console.error("Model loading error:", error);
    });

  startButton.addEventListener("click", async () => {
    if (!modelsLoaded) {
      showMessage("Please wait, models are still loading...");
      return;
    }
    
    videoContainer.style.display = "flex";
    startButton.style.display = "none";
    endButton.style.display = "inline-block";
    
    if (!webcamStarted) {
      startWebcam();
      webcamStarted = true;
    }
  });

  endButton.addEventListener("click", () => {
    videoContainer.style.display = "none";
    startButton.style.display = "inline-block";
    endButton.style.display = "none";
    stopWebcam();
  });

  function startWebcam() {
    navigator.mediaDevices
      .getUserMedia({
        video: true,
        audio: false,
      })
      .then((stream) => {
        video.srcObject = stream;
        window.videoStream = stream;
        showMessage("Camera started successfully!");
      })
      .catch((error) => {
        console.error("Camera error:", error);
        showMessage("Error: Could not access camera. Please allow camera permissions.");
      });
  }

  async function getLabeledFaceDescriptions() {
    const labeledDescriptors = [];
    detectedFaces = []; // Reset detected faces

    for (const label of labels) {
      const descriptions = [];

      for (let i = 1; i <= 5; i++) {
        try {
          const img = await faceapi.fetchImage(
            `resources/labels/${label}/${i}.png`
          );
          const detections = await faceapi
            .detectSingleFace(img)
            .withFaceLandmarks()
            .withFaceDescriptor();

          if (detections) {
            descriptions.push(detections.descriptor);
          } else {
            console.log(`No face detected in ${label}/${i}.png`);
          }
        } catch (error) {
          console.error(`Error processing ${label}/${i}.png:`, error);
        }
      }

      if (descriptions.length > 0) {
        labeledDescriptors.push(
          new faceapi.LabeledFaceDescriptors(label, descriptions)
        );
      }
    }

    console.log(`Loaded ${labeledDescriptors.length} face profiles`);
    return labeledDescriptors;
  }

  video.addEventListener("play", async () => {
    const labeledFaceDescriptors = await getLabeledFaceDescriptions();
    
    if (labeledFaceDescriptors.length === 0) {
      showMessage("Warning: No student face profiles found! Please register student faces first.");
      return;
    }
    
    const faceMatcher = new faceapi.FaceMatcher(labeledFaceDescriptors, 0.6);

    const canvas = faceapi.createCanvasFromMedia(video);
    videoContainer.appendChild(canvas);

    const displaySize = { width: video.width, height: video.height };
    faceapi.matchDimensions(canvas, displaySize);

    setInterval(async () => {
      const detections = await faceapi
        .detectAllFaces(video)
        .withFaceLandmarks()
        .withFaceDescriptors();

      const resizedDetections = faceapi.resizeResults(detections, displaySize);

      canvas.getContext("2d").clearRect(0, 0, canvas.width, canvas.height);

      const results = resizedDetections.map((d) => {
        return faceMatcher.findBestMatch(d.descriptor);
      });
      
      // Update detected faces list (only keep valid matches, not "unknown")
      detectedFaces = results
        .map((result) => result.label)
        .filter((label) => label !== "unknown");
      
      // Update attendance table
      markAttendance(detectedFaces);

      results.forEach((result, i) => {
        const box = resizedDetections[i].detection.box;
        const label = result.toString();
        const drawBox = new faceapi.draw.DrawBox(box, {
          label: label,
        });
        drawBox.draw(canvas);
      });
    }, 100);
  });
}

function sendAttendanceDataToServer() {
  const attendanceData = [];
  
  // Get course and unit from the dropdowns
  const courseSelect = document.getElementById("courseSelect");
  const unitSelect = document.getElementById("unitSelect");
  
  const course = courseSelect ? courseSelect.value : '';
  const unit = unitSelect ? unitSelect.value : '';

 if (!course || !unit) {
    showMessage("Error: Please select department and course first!");
    return;
}

console.log("Selected Department:", course);
console.log("Selected Course:", unit);


  // Collect attendance data from the table
  document
    .querySelectorAll("#studentTableContainer tr")
    .forEach((row, index) => {
      if (index === 0) return; // Skip header row
      
      const cells = row.cells;
      if (!cells || cells.length < 6) return; // Validate row has enough cells
      
      const studentID = cells[0].innerText.trim();
      const attendanceStatus = cells[5].innerText.trim();

      // Only send if we have valid data
      if (studentID && attendanceStatus) {
        attendanceData.push({ 
          studentID: studentID, 
          course: course,        // From dropdown, not from table
          unit: unit,            // From dropdown, not from table
          attendanceStatus: attendanceStatus 
        });
      }
    });

  if (attendanceData.length === 0) {
    showMessage("Error: No attendance data to send. Please ensure the table is populated.");
    return;
  }

  console.log("Sending attendance data:", attendanceData);

  const xhr = new XMLHttpRequest();
  console.log("Posting to: " + xhr.responseURL);
console.log("Data being sent:", JSON.stringify(attendanceData));

  xhr.open("POST", "resources/pages/lecture/handle_attendance.php", true);
  xhr.onerror = function() {
    showMessage("Network Error: Could not connect to server");
    console.error("XHR Error - check network tab");
};

xhr.ontimeout = function() {
    showMessage("Request timed out");
};

  xhr.setRequestHeader("Content-Type", "application/json");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        try {
          const response = JSON.parse(xhr.responseText);

          if (response.status === "success") {
            showMessage(
              response.message || "Attendance recorded successfully."
            );
            // Optionally reload the page or clear the form
            setTimeout(() => {
              location.reload();
            }, 2000);
          } else {
            showMessage(
              response.message ||
                "An error occurred while recording attendance."
            );
          }
        } catch (e) {
          showMessage("Error: Failed to parse the response from the server.");
          console.error("Parse error:", e);
          console.log("Server response:", xhr.responseText);
        }
      } else {
        showMessage(
          "Error: Unable to record attendance. HTTP Status: " + xhr.status
        );
        console.error("HTTP Error", xhr.status, xhr.statusText);
      }
    }
  };

  xhr.send(JSON.stringify(attendanceData));
}


function showMessage(message) {
  var messageDiv = document.getElementById("messageDiv");
  messageDiv.style.display = "block";
  messageDiv.innerHTML = message;
  console.log(message);
  messageDiv.style.opacity = 1;
  setTimeout(function () {
    messageDiv.style.opacity = 0;
    setTimeout(function() {
      messageDiv.style.display = "none";
    }, 500);
  }, 5000);
}

function stopWebcam() {
  if (window.videoStream) {
    const tracks = window.videoStream.getTracks();

    tracks.forEach((track) => {
      track.stop();
    });

    const video = document.getElementById("video");
    video.srcObject = null;
    window.videoStream = null;
    
    showMessage("Camera stopped.");
  }
}

document.getElementById("endAttendance").addEventListener("click", function () {
  if (confirm("Are you sure you want to submit attendance? This action cannot be undone.")) {
    sendAttendanceDataToServer();
    const videoContainer = document.querySelector(".video-container");
    videoContainer.style.display = "none";
    stopWebcam();
  }
});
