import csv
import math
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.cm as cm

csv = csv.reader(open('output1.csv', 'r'))

header = csv.next()
(y_size, x_size, start_lat, start_lon, end_lat, end_lon) = header[1:7]
(start_lat, start_lon, end_lat, end_lon) = map(float, (start_lat, start_lon, end_lat, end_lon))
(y_size, x_size) = map(int, (y_size, x_size))

ys = range(0, y_size) * x_size
xs = reduce(lambda as, bs: as+bs, map(lambda x: [x]*y_size, range(0, x_size)))

#print len(xs)
#print len(ys)

vs_x = []
vs_y = []
for row in csv:
	row = map(float, row)
	if row[1] == 2000:
		if row[0] == -1:
			vs_x = row[2:]
		if row[0] == -2:
			vs_y = row[2:]

vs = []
for i in range(0,len(vs_x)):
	vs += [math.sqrt(vs_x[i]**2 + vs_y[i]**2)]

Z = []

for i in range(0, y_size):
	Z += [[]]
	for j in range(0, x_size):
		Z[i] += [vs[i+y_size*j]]

#print Z

plt.figure(frameon=False, edgecolor=None)
plt.xticks([])
plt.yticks([])
plt.contourf(Z, 50, nchunk=5, cmap=cm.gist_rainbow_r, norm=matplotlib.colors.Normalize(vmin=0,vmax=150))
plt.quiver(xs, ys, vs_x, vs_y, linewidths=0.1, color='k')
plt.savefig('./graph1.png')

plt.figure()
plt.xticks([])
plt.yticks([])
cs = plt.contour(Z, 5, colors='k', linewidths=1.5, linestyles='dashed')
plt.clabel(cs, fontsize=10, fmt='%1.0f')
plt.savefig('./graph2.png')
	



