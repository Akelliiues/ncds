using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Text;
using System.Threading.Tasks;
using System.Windows.Forms;

[assembly: System.Reflection.AssemblyTitle("NCDs JHCIS Bridge Test")]
[assembly: System.Reflection.AssemblyProduct("NCDs JHCIS Bridge Test")]
[assembly: System.Reflection.AssemblyVersion("0.1.0.0")]

namespace NCDsJhcisBridgeTest
{
    internal static class Program
    {
        [STAThread]
        private static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new TestForm());
        }
    }

    internal sealed class TestForm : Form
    {
        private readonly TextBox host = new TextBox { Text = "127.0.0.1" };
        private readonly NumericUpDown port = new NumericUpDown { Minimum = 1, Maximum = 65535, Value = 3307 };
        private readonly TextBox database = new TextBox { Text = "jhcisdb" };
        private readonly TextBox user = new TextBox { Text = "root" };
        private readonly TextBox password = new TextBox { UseSystemPasswordChar = true };
        private readonly TextBox mysqlPath = new TextBox { Text = @"C:\Program Files\JHCIS\MySQL5.6\bin\mysql.exe" };
        private readonly Button testButton = new Button { Text = "ทดสอบการเชื่อมต่อ (อ่านอย่างเดียว)", Height = 42 };
        private readonly TextBox output = new TextBox { Multiline = true, ReadOnly = true, ScrollBars = ScrollBars.Vertical };

        public TestForm()
        {
            Text = "NCDs JHCIS Bridge Test — แยกจาก Red Alert Station";
            StartPosition = FormStartPosition.CenterScreen;
            MinimumSize = new Size(760, 610);
            Size = new Size(820, 650);
            Font = new Font("Tahoma", 10F);

            var warning = new Label {
                Text = "โหมดทดสอบแยก • ไม่แก้ข้อมูล JHCIS • ไม่เกี่ยวข้องกับระบบแจ้งเตือน • ไม่ทำงานอัตโนมัติ",
                AutoSize = false, Height = 48, Dock = DockStyle.Top, TextAlign = ContentAlignment.MiddleCenter,
                BackColor = Color.FromArgb(255, 247, 220), ForeColor = Color.FromArgb(120, 75, 0), Font = new Font(Font, FontStyle.Bold)
            };

            var form = new TableLayoutPanel { Dock = DockStyle.Top, Height = 265, Padding = new Padding(18), ColumnCount = 2, RowCount = 7 };
            form.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 175));
            form.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
            AddRow(form, 0, "MySQL Client", mysqlPath);
            AddRow(form, 1, "Host", host);
            AddRow(form, 2, "Port", port);
            AddRow(form, 3, "Database", database);
            AddRow(form, 4, "User", user);
            AddRow(form, 5, "Password", password);
            form.Controls.Add(testButton, 1, 6);
            testButton.Dock = DockStyle.Fill;

            output.Dock = DockStyle.Fill;
            output.BackColor = Color.FromArgb(28, 30, 36);
            output.ForeColor = Color.FromArgb(220, 235, 225);
            output.Font = new Font("Consolas", 10F);
            output.Text = "พร้อมทดสอบ ฐานเริ่มต้นคือฐานแยกที่พอร์ต 3307\r\n";

            Controls.Add(output);
            Controls.Add(form);
            Controls.Add(warning);
            testButton.Click += async delegate { await TestConnection(); };
        }

        private static void AddRow(TableLayoutPanel panel, int row, string label, Control control)
        {
            panel.RowStyles.Add(new RowStyle(SizeType.Absolute, 34));
            panel.Controls.Add(new Label { Text = label, Dock = DockStyle.Fill, TextAlign = ContentAlignment.MiddleLeft }, 0, row);
            control.Dock = DockStyle.Fill;
            panel.Controls.Add(control, 1, row);
        }

        private async Task TestConnection()
        {
            testButton.Enabled = false;
            output.Text = "กำลังทดสอบ...\r\n";
            try
            {
                if (!File.Exists(mysqlPath.Text.Trim()))
                    throw new FileNotFoundException("ไม่พบ mysql.exe", mysqlPath.Text.Trim());

                string db = SafeIdentifier(database.Text.Trim());
                string query = "SELECT CONCAT('MySQL: ',VERSION()); " +
                    "SELECT CONCAT('Database: ',DATABASE()); " +
                    "SELECT CONCAT('PCU: ',pcucodeperson,' | persons: ',COUNT(*)) FROM person GROUP BY pcucodeperson ORDER BY COUNT(*) DESC LIMIT 1; " +
                    "SELECT CONCAT('NCD screens: ',COUNT(*)) FROM ncd_person_ncd_screen;";

                var start = new ProcessStartInfo {
                    FileName = mysqlPath.Text.Trim(),
                    Arguments = "--connect-timeout=5 --protocol=tcp -h " + QuoteArg(host.Text.Trim()) +
                        " -P " + ((int)port.Value).ToString() + " -u " + QuoteArg(user.Text.Trim()) +
                        " --default-character-set=utf8 " + QuoteArg(db) + " -N -e " + QuoteArg(query),
                    UseShellExecute = false,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    CreateNoWindow = true,
                    StandardOutputEncoding = Encoding.UTF8,
                    StandardErrorEncoding = Encoding.UTF8
                };
                start.EnvironmentVariables["MYSQL_PWD"] = password.Text;

                string stdout = "", stderr = "";
                int exitCode = -1;
                await Task.Run(() => {
                    using (var process = Process.Start(start)) {
                        stdout = process.StandardOutput.ReadToEnd();
                        stderr = process.StandardError.ReadToEnd();
                        if (!process.WaitForExit(10000)) {
                            process.Kill();
                            throw new TimeoutException("หมดเวลารอการเชื่อมต่อ 10 วินาที");
                        }
                        exitCode = process.ExitCode;
                    }
                });

                if (exitCode != 0) throw new Exception(stderr.Trim());
                output.Text = "เชื่อมต่อสำเร็จ (READ ONLY)\r\n\r\n" + stdout.Replace("\n", "\r\n");
            }
            catch (Exception ex)
            {
                output.Text = "เชื่อมต่อไม่สำเร็จ\r\n\r\n" + ex.Message;
            }
            finally { testButton.Enabled = true; }
        }

        private static string SafeIdentifier(string value)
        {
            if (string.IsNullOrWhiteSpace(value)) throw new ArgumentException("กรุณาระบุชื่อฐานข้อมูล");
            foreach (char c in value)
                if (!(char.IsLetterOrDigit(c) || c == '_')) throw new ArgumentException("ชื่อฐานข้อมูลมีอักขระที่ไม่รองรับ");
            return value;
        }

        private static string QuoteArg(string value)
        {
            return "\"" + (value ?? "").Replace("\\", "\\\\").Replace("\"", "\\\"") + "\"";
        }
    }
}
